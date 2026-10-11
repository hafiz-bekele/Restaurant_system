<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierController extends Controller
{
    public function orders(): JsonResponse
    {
        $orders = Order::with(['items', 'table', 'user', 'payment', 'activeWaiter.waiter'])
            ->latest()
            ->limit(50)
            ->get();

        return $this->success(OrderResource::collection($orders));
    }

    public function confirmPayment(Request $request, Order $order): JsonResponse
    {
        $paymentType = $request->input('payment_type', $order->payment_method->value ?? 'cash');

        $order->update([
            'payment_status' => PaymentStatus::Paid,
        ]);

        Payment::create([
            'order_id'       => $order->id,
            'gateway'        => $paymentType,
            'amount'         => $order->total,
            'currency'       => 'USD',
            'status'         => PaymentStatus::Paid,
            'transaction_id' => 'CASH-' . strtoupper(bin2hex(random_bytes(4))),
        ]);

        return $this->success(new OrderResource($order->fresh(['items', 'table', 'user', 'payment'])), 'Payment confirmed successfully');
    }

    public function summary(): JsonResponse
    {
        $today = now()->startOfDay();

        $cashTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('payment_method', 'cash')
            ->sum('total');

        $digitalTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereIn('payment_method', ['stripe', 'chapa'])
            ->sum('total');

        $roomChargeTotal = (float) Order::where('created_at', '>=', $today)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('payment_method', 'room_charge')
            ->sum('total');

        $ordersCount = Order::where('created_at', '>=', $today)->count();
        $grandTotal = $cashTotal + $digitalTotal + $roomChargeTotal;

        return $this->success([
            'date'              => $today->toDateString(),
            'orders_count'      => $ordersCount,
            'cash_total'        => $cashTotal,
            'digital_total'     => $digitalTotal,
            'room_charge_total' => $roomChargeTotal,
            'grand_total'       => $grandTotal,
        ]);
    }
}
