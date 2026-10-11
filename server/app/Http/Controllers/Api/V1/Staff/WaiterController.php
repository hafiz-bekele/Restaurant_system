<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaiterController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function tables(): JsonResponse
    {
        $tables = RestaurantTable::withCount([
            'orders' => fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled']),
        ])->orderBy('name')->get();

        return $this->success($tables);
    }

    public function orders(): JsonResponse
    {
        $orders = Order::with(['items', 'table', 'user'])
            ->whereIn('status', [
                OrderStatus::Confirmed->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
            ])
            ->latest()
            ->get();

        return $this->success(OrderResource::collection($orders));
    }

    public function markServed(Request $request, Order $order): JsonResponse
    {
        $updated = $this->orderService->updateStatus(
            $order,
            OrderStatus::Delivered,
            $request->user()->id,
            'Order delivered and served to guest by waiter.'
        );

        return $this->success(new OrderResource($updated), 'Order marked as served');
    }

    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_id' => ['required', 'exists:tables,id'],
            'items'    => ['required', 'array', 'min:1'],
            'items.*.dish_id'  => ['required', 'exists:dishes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $table = RestaurantTable::findOrFail($data['table_id']);
        $hotelId = $table->hotel_id ?? app('current_hotel_id') ?? 1;

        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $table, $hotelId, $request) {
            $subtotal = 0;
            $orderItems = [];

            foreach ($data['items'] as $itemData) {
                $dish = \App\Models\Dish::findOrFail($itemData['dish_id']);
                $itemSubtotal = $dish->price * $itemData['quantity'];
                $subtotal += $itemSubtotal;
                $orderItems[] = [
                    'dish_id'       => $dish->id,
                    'name_snapshot' => $dish->name,
                    'unit_price'    => $dish->price,
                    'quantity'      => $itemData['quantity'],
                    'station'       => 'kitchen',
                ];
            }

            // REQ-6.2 Order Financial Calculation Formula:
            // Subtotal = sum(price * qty)
            // Service Charge = Subtotal * 10%
            // Taxable Basis = Subtotal + Service Charge
            // VAT = Taxable Basis * 15%
            // Total = Taxable Basis + VAT
            $serviceCharge = round($subtotal * 0.10, 2);
            $taxableBasis  = $subtotal + $serviceCharge;
            $tax           = round($taxableBasis * 0.15, 2);
            $total         = round($taxableBasis + $tax, 2);

            $order = Order::create([
                'hotel_id'       => $hotelId,
                'user_id'        => $request->user()->id,
                'table_id'       => $table->id,
                'type'           => \App\Enums\OrderType::DineIn,
                'status'         => \App\Enums\OrderStatus::Confirmed,
                'payment_status' => \App\Enums\PaymentStatus::Pending,
                'subtotal'       => $subtotal,
                'service_charge' => $serviceCharge,
                'tax'            => $tax,
                'total'          => $total,
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            // Assign this waiter immediately
            \App\Models\WaiterAssignment::create([
                'order_id'    => $order->id,
                'waiter_id'   => $request->user()->id,
                'status'      => 'assigned',
                'assigned_at' => now(),
            ]);

            $table->update(['status' => 'occupied']);

            return $this->success(new OrderResource($order->load('items', 'table')), 'Table order created and sent to kitchen KDS', 201);
        });
    }
}
