<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\WaiterAssignmentService;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly WaiterAssignmentService $waiterAssignmentService,
    ) {}

    public function createFromCart(Cart $cart, array $data, string $idempotencyKey): Order
    {
        // Check for duplicate submission
        $existing = Order::where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $deliveryFee = (float) ($data['delivery_fee'] ?? 0);
        $totals = $this->cartService->calculateTotals($cart, $deliveryFee);

        return DB::transaction(function () use ($cart, $data, $idempotencyKey, $totals) {
            $user = $cart->user_id ? null : (auth('sanctum')->user());
            $userId = $cart->user_id ?? $user?->id;

            $addressSnapshot = $data['address'] ?? [];
            if (!empty($data['customer_name'])) {
                $addressSnapshot['customer_name'] = $data['customer_name'];
            }
            if (!empty($data['customer_phone'])) {
                $addressSnapshot['customer_phone'] = $data['customer_phone'];
            }
            if (!empty($data['customer_email'])) {
                $addressSnapshot['customer_email'] = $data['customer_email'];
            }

            $order = Order::create([
                'user_id' => $userId,
                'table_id' => $data['table_id'] ?? null,
                'delivery_zone_id' => $data['delivery_zone_id'] ?? null,
                'type' => $data['type'],
                'status' => OrderStatus::Pending,
                'subtotal'       => $totals['subtotal'],
                'tax'            => $totals['tax'],
                'service_charge' => $totals['serviceCharge'],
                'delivery_fee'   => $totals['deliveryFee'],
                'tip' => $data['tip'] ?? 0,
                'discount' => $totals['discount'],
                'total' => $totals['total'] + ($data['tip'] ?? 0),
                'payment_method' => $data['payment_method'],
                'coupon_code' => $cart->coupon_code,
                'address_snapshot' => !empty($addressSnapshot) ? $addressSnapshot : null,
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'payment_status' => PaymentStatus::Pending,
            ]);

            // Snapshot order items from cart
            $cart->load(['items.dish', 'items.variant']);

            foreach ($cart->items as $item) {
                $price = (float) $item->dish->price;

                if ($item->variant) {
                    $price += (float) $item->variant->price_delta;
                }

                $addonTotal = collect($item->options ?? [])->sum('price');
                $price += $addonTotal;

                $order->items()->create([
                    'dish_id' => $item->dish_id,
                    'name_snapshot' => $item->dish->name,
                    'unit_price' => $price,
                    'quantity' => $item->quantity,
                    'options' => $item->options,
                    'station' => $item->dish->category?->name,
                ]);
            }

            // Record initial status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'status' => OrderStatus::Pending,
                'note' => 'Order placed',
            ]);

            // Record coupon usage
            if ($cart->coupon_code) {
                $coupon = Coupon::where('code', $cart->coupon_code)->first();

                if ($coupon) {
                    CouponUsage::create([
                        'coupon_id' => $coupon->id,
                        'user_id' => $userId,
                        'order_id' => $order->id,
                        'used_at' => now(),
                    ]);

                    $coupon->increment('used_count');
                }
            }

            // Clear cart
            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);

            event(new OrderPlaced($order));

            // REQ-7.1: auto-assign a waiter after the order is created
            $this->waiterAssignmentService->assign($order);

            return $order;
        });
    }

    public function updateStatus(Order $order, OrderStatus $newStatus, ?int $actorId = null, ?string $note = null): Order
    {
        DB::transaction(function () use ($order, $newStatus, $actorId, $note) {
            $order->update(['status' => $newStatus]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $actorId,
                'status' => $newStatus,
                'note' => $note,
            ]);
        });

        event(new OrderStatusChanged($order));

        return $order->fresh();
    }

    public function cancel(Order $order, ?string $reason = null): Order
    {
        if (! $order->canBeCancelled()) {
            throw new \RuntimeException('This order cannot be cancelled.');
        }

        return $this->updateStatus($order, OrderStatus::Cancelled, auth()->id(), $reason ?? 'Cancelled by customer');
    }
}
