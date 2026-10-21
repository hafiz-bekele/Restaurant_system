<?php

namespace App\Contracts\Payment;

interface PaymentGatewayInterface
{
    /**
     * Initialize a payment transaction with the gateway.
     *
     * @param array $data
     * @return array{checkout_url: string, tx_ref: string, mode: string, raw?: array}
     */
    public function initializePayment(array $data): array;

    /**
     * Verify payment status directly with the gateway.
     *
     * @param string $txRef
     * @return array{status: string, amount: float, currency: string, reference?: string, raw?: array}
     */
    public function verifyPayment(string $txRef): array;

    /**
     * Verify webhook signature with HMAC SHA256.
     */
    public function verifyWebhookSignature(string $rawPayload, string $signature): bool;
}
