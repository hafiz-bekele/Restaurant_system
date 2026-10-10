<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Models\Order;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeGateway implements PaymentGateway
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createIntent(Order $order): array
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => (int) ($order->total * 100),
            'currency' => strtolower(config('app.currency', 'usd')),
            'metadata' => ['order_ulid' => $order->ulid],
        ]);

        return [
            'payment_id' => $intent->id,
            'client_secret' => $intent->client_secret,
        ];
    }

    public function handleWebhook(string $rawPayload, string $signature): string
    {
        $event = Webhook::constructEvent(
            $rawPayload,
            $signature,
            config('services.stripe.webhook_secret')
        );

        if ($event->type === 'payment_intent.succeeded') {
            return $event->data->object->id;
        }

        throw new \RuntimeException("Unhandled Stripe event: {$event->type}");
    }

    public function refund(string $transactionId, float $amount): bool
    {
        $refund = $this->stripe->refunds->create([
            'payment_intent' => $transactionId,
            'amount' => (int) ($amount * 100),
        ]);

        return $refund->status === 'succeeded';
    }
}
