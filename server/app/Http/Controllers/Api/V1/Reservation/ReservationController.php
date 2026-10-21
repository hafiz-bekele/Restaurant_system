<?php

namespace App\Http\Controllers\Api\V1\Reservation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\AvailabilityRequest;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Requests\Reservation\UpdateReservationRequest;
use App\Http\Resources\V1\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        protected readonly ReservationService $reservationService
    ) {}

    /**
     * GET /api/v1/reservations/pricing
     * Returns durations, base rate, and day surcharges.
     */
    public function pricing(): JsonResponse
    {
        return $this->success($this->reservationService->getPricing());
    }

    /**
     * GET /api/v1/reservations/available-tables
     * Query tables with capacity >= party_size and no overlapping reservations.
     */
    public function availableTables(AvailabilityRequest $request): JsonResponse
    {
        $startAt       = $request->getStartAt();
        $durationHours = (int) $request->input('duration_hours', 1);
        $partySize     = (int) $request->input('party_size', 2);

        $tables = $this->reservationService->getAvailableTables($startAt, $durationHours, $partySize);

        return $this->success(
            $tables->map(fn ($t) => [
                'id'         => $t->id,
                'name'       => $t->name,
                'area'       => $t->area ?? 'indoor',
                'capacity'   => (int) $t->capacity,
                'features'   => $t->features ?? [],
                'is_active'  => (bool) ($t->is_active ?? true),
                'status'     => $t->status?->value ?? (string) $t->status,
                'position_x' => (float) $t->position_x,
                'position_y' => (float) $t->position_y,
            ])
        );
    }

    /**
     * Backward-compatible alias for legacy calls.
     */
    public function availability(AvailabilityRequest $request): JsonResponse
    {
        return $this->availableTables($request);
    }

    /**
     * POST /api/v1/reservations
     * Creates a pending reservation with a 10-minute hold and initiates Chapa checkout.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        try {
            $data   = array_merge($request->validated(), [
                'start_at' => $request->input('start_at'),
            ]);
            $userId = $request->user()?->id;

            $result = $this->reservationService->createPendingReservation($data, $userId);

            return $this->success([
                'reservation'  => new ReservationResource($result['reservation']),
                'checkout_url' => $result['checkout_url'],
                'tx_ref'       => $result['tx_ref'],
                'mode'         => $result['mode'],
            ], 'Reservation hold created. Please complete payment within 10 minutes.', 201);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);
            return $this->error('Failed to create reservation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/reservations/{ulid}
     * Returns reservation details with live countdown timer parameters.
     */
    public function show(string $identifier): JsonResponse
    {
        // Support either ULID or numeric ID
        $reservation = is_numeric($identifier)
            ? Reservation::with('table')->findOrFail($identifier)
            : Reservation::where('ulid', $identifier)->with('table')->firstOrFail();

        return $this->success(new ReservationResource($reservation));
    }

    /**
     * POST /api/v1/reservations/{ulid}/verify-payment
     * Verifies transaction with Chapa and confirms reservation.
     */
    public function verifyPayment(string $ulid): JsonResponse
    {
        try {
            $result = $this->reservationService->verifyAndConfirmPayment($ulid);

            return $this->success([
                'status'      => $result['status'], // 'paid' | 'pending' | 'failed'
                'message'     => $result['message'],
                'reservation' => new ReservationResource($result['reservation']),
            ], $result['message']);
        } catch (\Throwable $e) {
            report($e);
            return $this->error('Failed to verify payment: ' . $e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/reservations/{ulid}/cancel
     * Allows customer or staff to cancel an upcoming reservation.
     */
    public function cancel(Request $request, string $ulid): JsonResponse
    {
        $reservation = is_numeric($ulid)
            ? Reservation::findOrFail($ulid)
            : Reservation::where('ulid', $ulid)->firstOrFail();

        $reason = $request->input('reason', 'Customer requested cancellation');

        try {
            $cancelled = $this->reservationService->cancel($reservation, $reason);

            return $this->success(
                new ReservationResource($cancelled),
                'Reservation cancelled successfully'
            );
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * DELETE /api/v1/reservations/{ulid} (legacy destroy)
     */
    public function destroy(string $ulid): JsonResponse
    {
        return $this->cancel(request(), $ulid);
    }

    /**
     * PATCH /api/v1/reservations/{ulid}
     */
    public function update(UpdateReservationRequest $request, string $ulid): JsonResponse
    {
        $reservation = is_numeric($ulid)
            ? Reservation::findOrFail($ulid)
            : Reservation::where('ulid', $ulid)->firstOrFail();

        $reservation->update($request->validated());

        return $this->success(new ReservationResource($reservation->fresh('table')));
    }
}
