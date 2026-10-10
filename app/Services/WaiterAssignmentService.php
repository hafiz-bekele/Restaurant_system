<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WaiterAssignment;
use Illuminate\Support\Facades\DB;

/**
 * REQ-7.1: Intelligent Automatic Waiter Assignment Engine.
 *
 * Algorithm:
 *  1. Find all on-duty active waiters in the same hotel.
 *  2. Filter by floor section if the order is from a table with an area.
 *  3. Calculate workload W = count(active orders: pending|confirmed|preparing).
 *  4. Pick the waiter with min(W); ties broken by earliest last assignment (round-robin).
 *  5. Create a WaiterAssignment record.
 */
class WaiterAssignmentService
{
    public function assign(Order $order): ?WaiterAssignment
    {
        $hotelId = $order->user?->hotel_id ?? app('current_hotel_id');

        // Step 1: retrieve on-duty active waiters in this hotel
        $candidates = User::withoutGlobalScopes()
            ->role('waiter')
            ->where('status', 'active')
            ->where('on_duty', true)
            ->where('hotel_id', $hotelId)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Step 2: filter by section/area when order comes from a specific table area
        $tableArea = $order->table?->area;

        if ($tableArea) {
            $sectionCandidates = $candidates->filter(
                fn (User $u) => $u->section === $tableArea
            );

            if ($sectionCandidates->isNotEmpty()) {
                $candidates = $sectionCandidates;
            }
        }

        // Step 3 & 4: calculate workload, pick min — ties to earliest last assignment
        $waiterIds = $candidates->pluck('id');

        $workloads = DB::table('waiter_assignments as wa')
            ->join('orders as o', 'wa.order_id', '=', 'o.id')
            ->whereIn('wa.waiter_id', $waiterIds)
            ->whereIn('o.status', ['pending', 'confirmed', 'preparing'])
            ->where('wa.status', '!=', 'delivered')
            ->selectRaw('wa.waiter_id, COUNT(*) as active_orders, MAX(wa.assigned_at) as last_assigned')
            ->groupBy('wa.waiter_id')
            ->get()
            ->keyBy('waiter_id');

        $selected = $candidates
            ->map(function (User $waiter) use ($workloads) {
                $row = $workloads->get($waiter->id);

                return [
                    'waiter'        => $waiter,
                    'active_orders' => $row ? (int) $row->active_orders : 0,
                    'last_assigned' => $row ? $row->last_assigned : '1970-01-01',
                ];
            })
            ->sortBy([
                ['active_orders', 'asc'],
                ['last_assigned', 'asc'],
            ])
            ->first();

        if (! $selected) {
            return null;
        }

        // Step 5: create the assignment record
        return WaiterAssignment::create([
            'order_id'    => $order->id,
            'waiter_id'   => $selected['waiter']->id,
            'status'      => 'assigned',
            'assigned_at' => now(),
        ]);
    }

    /**
     * REQ-7.2: Managers can manually reassign.
     */
    public function reassign(Order $order, int $waiterId): WaiterAssignment
    {
        // Mark previous assignments as superseded
        WaiterAssignment::where('order_id', $order->id)
            ->where('status', 'assigned')
            ->update(['status' => 'reassigned']);

        return WaiterAssignment::create([
            'order_id'    => $order->id,
            'waiter_id'   => $waiterId,
            'status'      => 'assigned',
            'assigned_at' => now(),
        ]);
    }
}
