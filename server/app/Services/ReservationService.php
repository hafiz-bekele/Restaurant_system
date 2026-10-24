<?php

namespace App\Services;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Events\ReservationCreated;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationDaySurcharge;
use App\Models\ReservationPricing;
use App\Models\RestaurantTable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationService
{
    public function __construct(
        protected PaymentGatewayInterface $paymentGateway
    ) {}

    /**
     * Retrieve active reservation configuration.
     * Table reservations are complimentary (no per-hour fee or advance booking penalty).
     */
    public function getPricing(): array
    {
        return [
            'is_complimentary'   => true,
            'base_rate_per_hour' => 0.00,
            'durations'          => [
                ['duration_hours' => 1, 'price' => 0.00, 'title' => '1 Hour',  'subtitle' => 'Quick Dining / Lunch'],
                ['duration_hours' => 2, 'price' => 0.00, 'title' => '2 Hours', 'subtitle' => 'Standard Dining (Recommended)'],
                ['duration_hours' => 3, 'price' => 0.00, 'title' => '3 Hours', 'subtitle' => 'Celebration & Leisure'],
                ['duration_hours' => 4, 'price' => 0.00, 'title' => '4 Hours', 'subtitle' => 'Private Banquet / VIP Session'],
            ],
            'day_surcharges'     => [],
            'currency'           => 'ETB',
            'max_duration_hours' => 4,
            'max_advance_days'   => 14,
        ];
    }

    /**
     * Calculate exact price breakdown. Complimentary table reservations have 0.00 fee.
     */
    public function calculatePrice(int $durationHours, Carbon $startAt): array
    {
        if ($durationHours < 1 || $durationHours > 24) {
            throw new \InvalidArgumentException('Duration must be between 1 and 24 hours.');
        }

        $todayStart  = Carbon::now()->startOfDay();
        $resStartDay = $startAt->copy()->startOfDay();
        $daysAhead   = (int) $todayStart->diffInDays($resStartDay, false);
        $daysAhead   = max(0, $daysAhead);

        return [
            'duration_hours'    => $durationHours,
            'days_ahead'        => $daysAhead,
            'price_base'        => 0.00,
            'surcharge_percent' => 0.00,
            'surcharge_amount'  => 0.00,
            'total_price'       => 0.00,
            'currency'          => 'ETB',
        ];
    }

    /**
     * Query available tables that satisfy capacity and have NO overlapping holds or reservations.
     * Overlap rule: existing.start_at < requested.end_at AND existing.end_at > requested.start_at
     */
    public function getAvailableTables(Carbon $startAt, int $durationHours, int $partySize): Collection
    {
        $endAt = $startAt->copy()->addHours($durationHours);

        // Overlapping reservations: confirmed, active, or pending_payment with active hold
        $blockingTableIds = Reservation::where(function ($q) {
                $q->whereIn('status', [
                    ReservationStatus::Confirmed,
                    ReservationStatus::Active,
                    ReservationStatus::Pending, // backward compat
                ])
                ->orWhere(function ($sub) {
                    $sub->where('status', ReservationStatus::PendingPayment)
                        ->where('hold_expires_at', '>', Carbon::now());
                });
            })
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->pluck('table_id')
            ->unique();

        return RestaurantTable::where('is_active', true)
            ->where('capacity', '>=', $partySize)
            ->whereNotIn('id', $blockingTableIds)
            ->orderBy('capacity')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create an instant, confirmed table reservation directly without payment barriers.
     */
    public function createReservation(array $data, ?int $userId = null): array
    {
        $startAt       = Carbon::parse($data['start_at']);
        $durationHours = max(1, (int) ($data['duration_hours'] ?? 2));
        $partySize     = (int) $data['party_size'];
        $tableId       = (int) $data['table_id'];
        $endAt         = $startAt->copy()->addHours($durationHours);

        return DB::transaction(function () use ($data, $userId, $tableId, $partySize, $startAt, $endAt, $durationHours) {
            $table = RestaurantTable::where('id', $tableId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $table || $table->capacity < $partySize) {
                throw new \DomainException('The selected table is not suitable for your party size or is unavailable.');
            }

            // Check for overlapping confirmed or active reservations
            $hasOverlap = Reservation::where('table_id', $tableId)
                ->whereIn('status', [
                    ReservationStatus::Confirmed,
                    ReservationStatus::Active,
                    ReservationStatus::Pending,
                ])
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->exists();

            if ($hasOverlap) {
                throw new \DomainException('This table is already reserved for the selected dining window. Please choose another table or time.');
            }

            $customerName = trim($data['customer_name'] ?? $data['name'] ?? 'Guest Diner');

            $reservation = Reservation::create([
                'user_id'          => $userId,
                'table_id'         => $tableId,
                'customer_name'    => $customerName,
                'name'             => $customerName,
                'phone'            => $data['phone'],
                'email'            => $data['email'],
                'party_size'       => $partySize,
                'start_at'         => $startAt,
                'end_at'           => $endAt,
                'reserved_at'      => $startAt,
                'duration_hours'   => $durationHours,
                'duration'         => $durationHours * 60,
                'status'           => ReservationStatus::Confirmed,
                'price_base'       => 0.00,
                'surcharge_percent'=> 0.00,
                'surcharge_amount' => 0.00,
                'total_price'      => 0.00,
                'currency'         => 'ETB',
                'special_requests' => $data['special_requests'] ?? $data['notes'] ?? null,
                'notes'            => $data['special_requests'] ?? $data['notes'] ?? null,
                'payment_status'   => 'complimentary',
                'paid_at'          => Carbon::now(),
            ]);

            event(new ReservationCreated($reservation));

            return [
                'reservation'  => $reservation->load('table'),
                'checkout_url' => null,
                'tx_ref'       => 'RSV-' . $reservation->ulid,
                'mode'         => 'instant',
            ];
        });
    }

    /**
     * Backward-compatible alias for staff & admin controllers.
     */
    public function autoRedeemExpiredReservations(): void
    {
        $this->expireOverdueReservations();
    }

    /**
     * Create reservation with pending_payment status, 10-minute hold, and initialize Chapa transaction.
     * Uses DB transaction and SELECT ... FOR UPDATE to prevent double-booking.
     */
    public function createPendingReservation(array $data, ?int $userId = null): array
    {
        $startAt       = Carbon::parse($data['start_at']);
        $durationHours = (int) $data['duration_hours'];
        $partySize     = (int) $data['party_size'];
        $tableId       = (int) $data['table_id'];

        if ($durationHours < 1 || $durationHours > 24) {
            throw new \InvalidArgumentException('Reservation duration cannot exceed 24 hours.');
        }

        $endAt = $startAt->copy()->addHours($durationHours);

        // Recalculate price on the server — never trust the client
        $priceInfo = $this->calculatePrice($durationHours, $startAt);

        return DB::transaction(function () use ($data, $userId, $tableId, $partySize, $startAt, $endAt, $durationHours, $priceInfo) {
            // Lock table row to prevent concurrent race conditions
            $table = RestaurantTable::where('id', $tableId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $table || $table->capacity < $partySize) {
                throw new \DomainException('The selected table is not suitable for your party size or is unavailable.');
            }

            // Re-verify overlap inside the locked transaction
            $hasOverlap = Reservation::where('table_id', $tableId)
                ->where(function ($q) {
                    $q->whereIn('status', [
                        ReservationStatus::Confirmed,
                        ReservationStatus::Active,
                        ReservationStatus::Pending,
                    ])
                    ->orWhere(function ($sub) {
                        $sub->where('status', ReservationStatus::PendingPayment)
                            ->where('hold_expires_at', '>', Carbon::now());
                    });
                })
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->exists();

            if ($hasOverlap) {
                throw new \DomainException('This table was just reserved by another customer for overlapping hours. Please choose another table.');
            }

            // 10-minute hold
            $holdExpiresAt = Carbon::now()->addMinutes(10);

            $reservation = Reservation::create([
                'user_id'          => $userId,
                'table_id'         => $tableId,
                'customer_name'    => $data['customer_name'] ?? $data['name'],
                'name'             => $data['customer_name'] ?? $data['name'],
                'phone'            => $data['phone'],
                'email'            => $data['email'],
                'party_size'       => $partySize,
                'start_at'         => $startAt,
                'end_at'           => $endAt,
                'reserved_at'      => $startAt,
                'duration_hours'   => $durationHours,
                'duration'         => $durationHours * 60,
                'status'           => ReservationStatus::PendingPayment,
                'hold_expires_at'  => $holdExpiresAt,
                'price_base'       => $priceInfo['price_base'],
                'surcharge_percent'=> $priceInfo['surcharge_percent'],
                'surcharge_amount' => $priceInfo['surcharge_amount'],
                'total_price'      => $priceInfo['total_price'],
                'currency'         => 'ETB',
                'special_requests' => $data['special_requests'] ?? $data['notes'] ?? null,
                'notes'            => $data['special_requests'] ?? $data['notes'] ?? null,
            ]);

            $txRef = 'RSV-' . $reservation->ulid;

            // Prepare Chapa initialization URLs
            $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
            $returnUrl   = "{$frontendUrl}/reservations?confirmation={$reservation->ulid}";
            $callbackUrl = url('/api/v1/webhooks/chapa');

            $nameParts = explode(' ', trim($reservation->customer_name));
            $firstName = $nameParts[0] ?: 'Guest';
            $lastName  = count($nameParts) > 1 ? end($nameParts) : 'Diner';

            $chapaInit = $this->paymentGateway->initializePayment([
                'amount'        => $reservation->total_price,
                'currency'      => 'ETB',
                'email'         => $reservation->email,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'phone_number'  => $reservation->phone,
                'tx_ref'        => $txRef,
                'return_url'    => $returnUrl,
                'callback_url'  => $callbackUrl,
                'customization' => [
                    'title'       => 'Taste & Stay Table Reservation',
                    'description' => "Booking for Table {$table->name} ({$durationHours}h)",
                ],
            ]);

            // Save in payments table linked to reservation
            Payment::create([
                'reservation_id' => $reservation->id,
                'gateway'        => 'chapa',
                'tx_ref'         => $txRef,
                'amount'         => $reservation->total_price,
                'currency'       => 'ETB',
                'status'         => PaymentStatus::Pending,
                'raw_payload'    => $chapaInit['raw'] ?? null,
            ]);

            return [
                'reservation'  => $reservation->load('table'),
                'checkout_url' => $chapaInit['checkout_url'],
                'tx_ref'       => $txRef,
                'mode'         => $chapaInit['mode'] ?? 'live',
            ];
        });
    }

    /**
     * Verify payment status directly with Chapa and confirm reservation idempotently.
     */
    public function verifyAndConfirmPayment(string $ulid): array
    {
        $reservation = Reservation::where('ulid', $ulid)->with('table')->firstOrFail();

        // If already confirmed, return confirmed state
        if ($reservation->isPaid() && $reservation->status !== ReservationStatus::PendingPayment) {
            return [
                'status'      => 'paid',
                'reservation' => $reservation,
                'message'     => 'Reservation payment verified.',
            ];
        }

        $txRef = 'RSV-' . $reservation->ulid;
        $verification = $this->paymentGateway->verifyPayment($txRef);

        if (($verification['status'] ?? '') === 'success') {
            $this->markAsConfirmed($reservation, $verification);

            return [
                'status'      => 'paid',
                'reservation' => $reservation->fresh('table'),
                'message'     => 'Payment verified successfully and reservation confirmed.',
            ];
        }

        // Check if hold has expired
        if ($reservation->isHoldExpired()) {
            $reservation->update(['status' => ReservationStatus::Cancelled]);
            return [
                'status'      => 'failed',
                'reservation' => $reservation->fresh('table'),
                'message'     => 'The 10-minute table hold expired before payment was completed.',
            ];
        }

        return [
            'status'      => 'pending',
            'reservation' => $reservation,
            'message'     => 'Payment verification is pending from the gateway.',
        ];
    }

    /**
     * Mark reservation as confirmed, apply timing rules, and record payment.
     */
    public function markAsConfirmed(Reservation $reservation, array $verificationData = []): void
    {
        DB::transaction(function () use ($reservation, $verificationData) {
            $now = Carbon::now();

            // Timing rules:
            // If start_at is within 15 minutes of now or has passed, countdown begins at paid_at
            $startAt = $reservation->start_at;
            $endAt   = $reservation->end_at;

            $isToday = $startAt->isToday();
            if ($isToday && $startAt->diffInMinutes($now, false) >= -15) {
                $startAt = $now;
                $endAt   = $now->copy()->addHours($reservation->duration_hours);
            }

            $reservation->update([
                'status'         => ReservationStatus::Confirmed,
                'paid_at'        => $now,
                'start_at'       => $startAt,
                'end_at'         => $endAt,
                'payment_method' => 'chapa',
                'payment_status' => 'paid',
            ]);

            // Update payment record
            Payment::updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'gateway'         => 'chapa',
                    'tx_ref'          => 'RSV-' . $reservation->ulid,
                    'chapa_reference' => $verificationData['reference'] ?? null,
                    'amount'          => $reservation->total_price,
                    'currency'        => 'ETB',
                    'status'          => PaymentStatus::Paid,
                    'raw_payload'     => $verificationData['raw'] ?? null,
                ]
            );

            event(new ReservationCreated($reservation));
        });
    }

    /**
     * Process Chapa webhook payload.
     */
    public function handleWebhook(string $rawPayload, string $signature): array
    {
        if (! $this->paymentGateway->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('Chapa webhook signature verification failed.');
            throw new \InvalidArgumentException('Invalid Chapa webhook signature.');
        }

        $payload = json_decode($rawPayload, true) ?? [];
        $txRef   = $payload['tx_ref'] ?? '';

        if (! str_starts_with($txRef, 'RSV-')) {
            return ['status' => 'ignored', 'message' => 'Not a reservation transaction.'];
        }

        $ulid = substr($txRef, 4);
        $reservation = Reservation::where('ulid', $ulid)->first();

        if (! $reservation) {
            Log::warning("Reservation not found for Chapa webhook tx_ref: {$txRef}");
            return ['status' => 'not_found'];
        }

        // Idempotency: if already confirmed, return success immediately
        if ($reservation->isPaid() && $reservation->status !== ReservationStatus::PendingPayment) {
            return ['status' => 'already_processed'];
        }

        // Verify with Chapa API directly before confirming
        $verification = $this->paymentGateway->verifyPayment($txRef);
        if (($verification['status'] ?? '') === 'success') {
            $this->markAsConfirmed($reservation, $verification);
            return ['status' => 'confirmed'];
        }

        return ['status' => 'failed'];
    }

    /**
     * Expire active reservations reaching end_at, and cancel expired pending payment holds.
     */
    public function expireOverdueReservations(): array
    {
        $now = Carbon::now();

        // 1. Expire confirmed/active reservations past end_at
        $expiredCount = Reservation::whereIn('status', [
                ReservationStatus::Confirmed,
                ReservationStatus::Active,
            ])
            ->where('end_at', '<=', $now)
            ->update([
                'status' => ReservationStatus::Expired,
            ]);

        // 2. Release pending payment holds older than 10 minutes
        $cancelledHoldsCount = Reservation::where('status', ReservationStatus::PendingPayment)
            ->where('hold_expires_at', '<=', $now)
            ->update([
                'status' => ReservationStatus::Cancelled,
            ]);

        return [
            'expired_reservations' => $expiredCount,
            'cancelled_holds'      => $cancelledHoldsCount,
        ];
    }

    /**
     * Cancel a reservation by customer before start_at.
     */
    public function cancel(Reservation $reservation, ?string $reason = null): Reservation
    {
        if (in_array($reservation->status, [ReservationStatus::Cancelled, ReservationStatus::Expired])) {
            throw new \DomainException('Reservation cannot be cancelled in its current state.');
        }

        $reservation->update([
            'status'           => ReservationStatus::Cancelled,
            'rejection_reason' => $reason,
        ]);

        return $reservation->fresh('table');
    }
}
