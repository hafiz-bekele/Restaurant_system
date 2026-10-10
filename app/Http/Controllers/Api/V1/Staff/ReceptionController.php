<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
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
        $todayReservations = Reservation::with(['table:id,name,area,capacity'])
            ->whereDate('reserved_at', today())
            ->whereNotIn('status', [ReservationStatus::Cancelled->value])
            ->orderBy('reserved_at')
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'phone'       => $r->phone,
                'email'       => $r->email,
                'party_size'  => $r->party_size,
                'reserved_at' => $r->reserved_at,
                'status'      => $r->status instanceof ReservationStatus ? $r->status->value : $r->status,
                'table'       => $r->table,
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
        ];

        return $this->success(compact('todayReservations', 'tables', 'summary'));
    }

    /**
     * Check a guest in for their reservation and mark table occupied.
     */
    public function checkIn(Request $request, Reservation $reservation): JsonResponse
    {
        if (! in_array(
            $reservation->status instanceof ReservationStatus ? $reservation->status->value : $reservation->status,
            ['pending', 'confirmed']
        )) {
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
     * Create a walk-in dine-in order for a table (no reservation needed).
     */
    public function walkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_id'   => 'required|integer|exists:tables,id',
            'party_size' => 'required|integer|min:1|max:50',
            'notes'      => 'nullable|string|max:500',
        ]);

        $table = RestaurantTable::findOrFail($data['table_id']);

        if (! $table->isAvailable()) {
            return $this->error("Table {$table->name} is currently {$table->status->value}.", 422);
        }

        $order = DB::transaction(function () use ($data, $table) {
            $order = Order::create([
                'ulid'             => (string) Str::ulid(),
                'user_id'          => null,
                'table_id'         => $table->id,
                'type'             => OrderType::DineIn,
                'status'           => OrderStatus::Confirmed,
                'subtotal'         => 0,
                'tax'              => 0,
                'delivery_fee'     => 0,
                'tip'              => 0,
                'discount'         => 0,
                'total'            => 0,
                'payment_status'   => PaymentStatus::Pending,
                'payment_method'   => 'cash',
                'notes'            => $data['notes'] ?? null,
                'idempotency_key'  => (string) Str::uuid(),
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'user_id'    => auth()->id(),
                'status'     => OrderStatus::Confirmed,
                'note'       => "Walk-in party of {$data['party_size']}",
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
