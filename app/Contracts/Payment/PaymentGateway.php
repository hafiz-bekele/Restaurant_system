<?php

namespace App\Contracts\Payment;

use App\Models\Order;

interface PaymentGateway
{
    /**
     * Create a payment intent and return gateway-specific data for the frontend.
     *
     * @return array{client_secret?: string, checkout_url?: string, payment_id: string}
     */
    public function createIntent(Order $order): array;

    /**
     * Verify and process an incoming webhook payload.
     * Returns the transaction ID on success.
     */
    public function handleWebhook(string $rawPayload, string $signature): string;

    /**
     * Issue a full or partial refund for a transaction.
     */
    public function refund(string $transactionId, float $amount): bool;
}
