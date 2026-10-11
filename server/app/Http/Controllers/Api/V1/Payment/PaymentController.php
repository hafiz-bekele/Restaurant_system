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
            'order_id'       => $order->id,
            'gateway'        => config('services.payment.default', 'stripe'),
            'transaction_id' => $result['payment_id'],
            'amount'         => $order->total,
            'currency'       => config('app.currency', 'USD'),
            'status'         => PaymentStatus::Pending,
        ]);

        return $this->success($result, 'Payment intent created');
    }

    /**
     * REQ-9.2: Chapa checkout_url flow.
     * Returns checkout_url for the guest to redirect to Chapa portal.
     */
    public function createChapaIntent(Request $request): JsonResponse
    {
        $request->validate(['order_ulid' => 'required|string|exists:orders,ulid']);

        $order = Order::where('ulid', $request->order_ulid)->firstOrFail();
        $gateway = app('payment.chapa');
        $result  = $gateway->createIntent($order);

        Payment::create([
            'order_id'       => $order->id,
            'gateway'        => 'chapa',
            'transaction_id' => $result['payment_id'],
            'amount'         => $order->total,
            'currency'       => 'ETB',
            'status'         => PaymentStatus::Pending,
        ]);

        return $this->success($result, 'Chapa payment initiated');
    }

    /**
     * Verify Chapa transaction status (telebirr, CBE, card).
     */
    public function verifyChapa(Request $request): JsonResponse
    {
        $request->validate([
            'order_ulid' => 'required_without:tx_ref|nullable|string|exists:orders,ulid',
            'tx_ref'     => 'required_without:order_ulid|nullable|string',
        ]);

        $order = null;
        if ($request->filled('order_ulid')) {
            $order = Order::where('ulid', $request->order_ulid)->firstOrFail();
        }

        $payment = null;
        if ($request->filled('tx_ref')) {
            $payment = Payment::where('transaction_id', $request->tx_ref)->first();
            if ($payment && ! $order) {
                $order = $payment->order;
            }
        }

        if ($order && ! $payment) {
            $payment = Payment::where('order_id', $order->id)->where('gateway', 'chapa')->latest()->first();
        }

        $txRef = $request->tx_ref ?: ($payment?->transaction_id ?: 'RES-'.$order->ulid);
        $gateway = app('payment.chapa');
        $verifyResult = $gateway->verify($txRef);

        if (($verifyResult['status'] ?? '') === 'success' || ($verifyResult['data']['status'] ?? '') === 'success') {
            if ($payment) {
                $payment->update([
                    'status' => PaymentStatus::Paid,
                    'payload' => $verifyResult,
                ]);
            } elseif ($order) {
                Payment::create([
                    'order_id'       => $order->id,
                    'gateway'        => 'chapa',
                    'transaction_id' => $txRef,
                    'amount'         => $order->total,
                    'currency'       => 'ETB',
                    'status'         => PaymentStatus::Paid,
                    'payload'        => $verifyResult,
                ]);
            }

            if ($order) {
                $order->update(['payment_status' => PaymentStatus::Paid]);
                if ($order->status === OrderStatus::Pending) {
                    app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed, null, 'Chapa payment confirmed');
                }
            }

            return $this->success([
                'order_ulid'     => $order?->ulid,
                'payment_status' => 'paid',
                'order_status'   => $order?->fresh()->status->value,
                'details'        => $verifyResult,
            ], 'Chapa payment successfully verified');
        }

        return $this->error('Chapa payment could not be verified', 422, $verifyResult);
    }

    /**
     * REQ-9.1 Room Charge: post order total to the guest's active room booking folio.
     */
    public function roomCharge(Request $request): JsonResponse
    {
        $request->validate([
            'order_ulid' => 'required|string|exists:orders,ulid',
            'booking_id' => 'required|integer|exists:room_bookings,id',
        ]);

        $order   = Order::where('ulid', $request->order_ulid)->firstOrFail();
        $booking = \App\Models\RoomBooking::findOrFail($request->booking_id);

        if (! in_array($booking->status, ['confirmed', 'checked_in'])) {
            return $this->error('No active room reservation found for room charge.', 422);
        }

        $txId = 'RC-'.$order->ulid;

        $booking->increment('paid_amount', (float) $order->total);

        Payment::create([
            'order_id'       => $order->id,
            'gateway'        => 'room_charge',
            'transaction_id' => $txId,
            'amount'         => $order->total,
            'currency'       => 'ETB',
            'status'         => PaymentStatus::Paid,
            'payload'        => ['booking_id' => $booking->id],
        ]);

        $order->update(['payment_status' => PaymentStatus::Paid]);
        app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed, null, 'Room charge applied');

        return $this->success(['transaction_id' => $txId], 'Room charge applied successfully');
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
