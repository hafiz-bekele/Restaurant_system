<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\WalkInRequest;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReceptionController extends Controller
{
    /**
     * Today's overview: reservations + table statuses.
     */
    public function dashboard(): JsonResponse
    {
        app(\App\Services\ReservationService::class)->autoRedeemExpiredReservations();

        $todayReservations = Reservation::with(['table:id,name,area,capacity'])
            ->whereDate('reserved_at', today())
            ->whereNotIn('status', [ReservationStatus::Cancelled->value])
            ->orderBy('reserved_at')
            ->get()
            ->map(fn ($r) => [
                'id'                        => $r->id,
                'name'                      => $r->name,
                'phone'                     => $r->phone,
                'email'                     => $r->email,
                'party_size'                => $r->party_size,
                'reserved_at'               => $r->reserved_at,
                'duration'                  => $r->duration,
                'status'                    => $r->status instanceof ReservationStatus ? $r->status->value : $r->status,
                'reservation_fee'           => (float) ($r->reservation_fee ?? 50.00),
                'initial_fee'               => (float) ($r->initial_fee ?? 50.00),
                'fee_status'                => $r->fee_status ?? 'pending',
                'redeemed_at'               => $r->redeemed_at,
                'redeem_amount'             => $r->redeem_amount !== null ? (float) $r->redeem_amount : null,
                'current_fee'               => (float) $r->current_fee,
                'elapsed_seconds'           => (int) $r->elapsed_seconds,
                'seconds_until_auto_redeem' => (int) $r->seconds_until_auto_redeem,
                'is_redeemed'               => $r->status === ReservationStatus::Redeemed || $r->fee_status === 'redeemed',
                'time'                      => $r->reserved_at ? $r->reserved_at->format('H:i') : null,
                'table_name'                => $r->table?->name,
                'table'                     => $r->table,
            ]);

        $tables = RestaurantTable::orderBy('area')->orderBy('name')->get()->map(fn ($t) => [
            'id'       => $t->id,
            'name'     => $t->name,
            'area'     => $t->area,
            'capacity' => $t->capacity,
            'status'   => $t->status instanceof TableStatus ? $t->status->value : $t->status,
            'qr_token' => $t->qr_token,
        ]);

        $summary = [
            'total_tables'     => $tables->count(),
            'available_tables' => $tables->where('status', 'available')->count(),
            'occupied_tables'  => $tables->where('status', 'occupied')->count(),
            'reserved_tables'  => $tables->where('status', 'reserved')->count(),
            'total_today'      => $todayReservations->count(),
            'confirmed_today'  => $todayReservations->where('status', 'confirmed')->count(),
            'redeemed_today'   => $todayReservations->where('status', 'redeemed')->count(),
        ];

        $reservations = $todayReservations;
        return $this->success(compact('todayReservations', 'reservations', 'tables', 'summary'));
    }

    /**
     * Check a guest in for their reservation and mark table occupied.
     */
    public function checkIn(Request $request, Reservation $reservation): JsonResponse
    {
        $currentStatus = $reservation->status instanceof ReservationStatus
            ? $reservation->status->value
            : $reservation->status;

        if (! in_array($currentStatus, ['pending', 'confirmed'])) {
            return $this->error('Reservation cannot be checked in with current status.', 422);
        }

        DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => ReservationStatus::Confirmed]);

            if ($reservation->table_id) {
                RestaurantTable::where('id', $reservation->table_id)
                    ->update(['status' => TableStatus::Occupied]);
            }
        });

        ActivityLog::record('reservation.checked_in', $reservation, ['name' => $reservation->name]);

        return $this->success(['status' => 'confirmed'], 'Guest checked in successfully');
    }

    /**
     * Mark a reservation as no-show and free the table.
     */
    public function noShow(Reservation $reservation): JsonResponse
    {
        DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => ReservationStatus::NoShow]);

            if ($reservation->table_id) {
                RestaurantTable::where('id', $reservation->table_id)
                    ->update(['status' => TableStatus::Available]);
            }
        });

        ActivityLog::record('reservation.no_show', $reservation);

        return $this->success(null, 'Marked as no-show');
    }

    /**
     * Redeem the reservation fee (after 2 hours or on check-in/checkout).
     */
    public function redeem(Reservation $reservation): JsonResponse
    {
        $reservation->redeem();
        ActivityLog::record('reservation.redeemed', $reservation, ['fee' => $reservation->redeem_amount]);

        return $this->success($reservation, 'Reservation fee redeemed successfully');
    }

    /**
     * Create a walk-in dine-in order for a table (no reservation needed).
     */
    public function walkIn(WalkInRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $table = RestaurantTable::findOrFail($data['table_id']);

        if (! $table->isAvailable()) {
            $status = $table->status instanceof TableStatus ? $table->status->value : $table->status;

            return $this->error("Table {$table->name} is currently {$status}.", 422);
        }

        $order = DB::transaction(function () use ($data, $table) {
            $order = Order::create([
                'ulid'            => (string) Str::ulid(),
                'user_id'         => null,
                'table_id'        => $table->id,
                'type'            => OrderType::DineIn,
                'status'          => OrderStatus::Confirmed,
                'subtotal'        => 0,
                'tax'             => 0,
                'service_charge'  => 0,
                'delivery_fee'    => 0,
                'tip'             => 0,
                'discount'        => 0,
                'total'           => 0,
                'payment_status'  => PaymentStatus::Pending,
                'payment_method'  => 'cash',
                'notes'           => $data['notes'] ?? null,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id'  => auth()->id(),
                'status'   => OrderStatus::Confirmed,
                'note'     => "Walk-in party of {$data['party_size']}",
            ]);

            $table->update(['status' => TableStatus::Occupied]);

            return $order;
        });

        ActivityLog::record('walkin.created', $order, [
            'table'      => $table->name,
            'party_size' => $data['party_size'],
        ]);

        return $this->success([
            'order_ulid'  => $order->ulid,
            'table'       => $table->name,
            'dine_in_url' => url("/qr/{$table->qr_token}"),
        ], 'Walk-in order created', 201);
    }

    /**
     * Release a table back to available (after guests leave).
     */
    public function releaseTable(RestaurantTable $table): JsonResponse
    {
        $table->update(['status' => TableStatus::Available]);
        ActivityLog::record('table.released', $table, ['name' => $table->name]);

        return $this->success(['status' => 'available'], "Table {$table->name} is now available");
    }

    /**
     * Get available tables for quick seating.
     */
    public function availableTables(Request $request): JsonResponse
    {
        $tables = RestaurantTable::where('status', TableStatus::Available)
            ->when($request->filled('capacity'), fn ($q) => $q->where('capacity', '>=', $request->capacity))
            ->when($request->filled('area'), fn ($q) => $q->where('area', $request->area))
            ->orderBy('capacity')
            ->get()
            ->map(fn ($t) => [
                'id'       => $t->id,
                'name'     => $t->name,
                'area'     => $t->area,
                'capacity' => $t->capacity,
                'qr_token' => $t->qr_token,
            ]);

        return $this->success($tables);
    }
}
