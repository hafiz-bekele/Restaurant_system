<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

class ChapaGateway implements PaymentGateway
{
    private string $baseUrl = 'https://api.chapa.co/v1';

    public function createIntent(Order $order): array
    {
        $txRef = 'RES-'.$order->ulid;

        $response = Http::withToken(config('services.chapa.secret'))
            ->post("{$this->baseUrl}/transaction/initialize", [
                'amount' => $order->total,
                'currency' => 'ETB',
                'tx_ref' => $txRef,
                'return_url' => config('services.chapa.return_url'),
                'callback_url' => config('services.chapa.webhook_url'),
                'customization' => ['title' => config('app.name')],
            ]);

        $response->throw();

        return [
            'payment_id' => $txRef,
            'checkout_url' => $response->json('data.checkout_url'),
        ];
    }

    public function handleWebhook(string $rawPayload, string $signature): string
    {
        $data = json_decode($rawPayload, true);

        $expectedSig = hash_hmac('sha256', $rawPayload, config('services.chapa.webhook_secret'));

        if (! hash_equals($expectedSig, $signature)) {
            throw new \RuntimeException('Invalid Chapa webhook signature');
        }

        if (($data['status'] ?? '') !== 'success') {
            throw new \RuntimeException('Chapa payment not successful');
        }

        return $data['tx_ref'];
    }

    public function refund(string $transactionId, float $amount): bool
    {
        // Chapa does not have a refund API yet — mark as manual
        return false;
    }
}
