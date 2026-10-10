<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function createIntent(Request $request): JsonResponse
    {
        $request->validate(['order_ulid' => 'required|string|exists:orders,ulid']);

        $order = Order::where('ulid', $request->order_ulid)->firstOrFail();
        $gateway = app(PaymentGateway::class);
        $result = $gateway->createIntent($order);

        Payment::create([
            'order_id' => $order->id,
            'gateway' => config('services.payment.default', 'stripe'),
            'transaction_id' => $result['payment_id'],
            'amount' => $order->total,
            'currency' => config('app.currency', 'USD'),
            'status' => PaymentStatus::Pending,
        ]);

        return $this->success($result, 'Payment intent created');
    }

    public function stripeWebhook(Request $request): JsonResponse
    {
        $this->handleWebhook($request, 'stripe', $request->header('Stripe-Signature', ''));

        return $this->success(null, 'OK');
    }

    public function chapaWebhook(Request $request): JsonResponse
    {
        $this->handleWebhook($request, 'chapa', $request->header('Chapa-Signature', ''));

        return $this->success(null, 'OK');
    }

    private function handleWebhook(Request $request, string $gatewayName, string $signature): void
    {
        $gateway = app("payment.{$gatewayName}");
        $transactionId = $gateway->handleWebhook($request->getContent(), $signature);

        $payment = Payment::where('transaction_id', $transactionId)->firstOrFail();
        $payment->update(['status' => PaymentStatus::Paid]);

        $order = $payment->order;
        $order->update(['payment_status' => PaymentStatus::Paid]);

        app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed, null, 'Payment confirmed');
    }
}
