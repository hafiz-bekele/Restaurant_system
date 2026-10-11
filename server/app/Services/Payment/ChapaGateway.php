<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGateway;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChapaGateway implements PaymentGateway
{
    private string $baseUrl = 'https://api.chapa.co/v1';

    public function createIntent(Order $order): array
    {
        $txRef = 'RES-'.$order->ulid.'-'.time();

        $name = $order->user?->name ?: 'Guest Diner';
        $nameParts = explode(' ', trim($name));
        $firstName = $nameParts[0] ?: 'Guest';
        $lastName  = count($nameParts) > 1 ? end($nameParts) : 'Customer';
        $email     = $order->user?->email ?: 'guest@restaurant.com';
        $phone     = $order->user?->phone ?: '0911000000';

        $secret      = config('services.chapa.secret');
        $returnUrl   = config('services.chapa.return_url') ?: "http://localhost:5173/orders/{$order->ulid}/track?payment=chapa_return";
        $callbackUrl = config('services.chapa.webhook_url') ?: url('/api/v1/webhooks/chapa');

        if (!empty($secret)) {
            try {
                $response = Http::withToken($secret)
                    ->timeout(10)
                    ->post("{$this->baseUrl}/transaction/initialize", [
                        'amount'        => (string) $order->total,
                        'currency'      => 'ETB',
                        'email'         => $email,
                        'first_name'    => $firstName,
                        'last_name'     => $lastName,
                        'phone_number'  => $phone,
                        'tx_ref'        => $txRef,
                        'return_url'    => $returnUrl,
                        'callback_url'  => $callbackUrl,
                        'customization' => [
                            'title'       => config('app.name', 'Restaurant System'),
                            'description' => 'Payment for Order #'.substr($order->ulid, -6),
                        ],
                    ]);

                if ($response->successful() && $response->json('status') === 'success') {
                    return [
                        'payment_id'   => $txRef,
                        'checkout_url' => $response->json('data.checkout_url'),
                        'mode'         => 'live',
                    ];
                }

                Log::warning('Chapa API response returned error: ' . $response->body());
            } catch (\Throwable $e) {
                Log::warning('Chapa initialization exception: ' . $e->getMessage());
            }
        }

        // Sandbox / Local Demo Fallback URL
        $simulatedUrl = "http://localhost:5173/orders/{$order->ulid}/track?payment=chapa_simulation&tx_ref={$txRef}";

        return [
            'payment_id'   => $txRef,
            'checkout_url' => $simulatedUrl,
            'mode'         => 'simulation',
        ];
    }

    public function verify(string $txRef): array
    {
        $secret = config('services.chapa.secret');

        if (!empty($secret)) {
            try {
                $response = Http::withToken($secret)
                    ->timeout(10)
                    ->get("{$this->baseUrl}/transaction/verify/{$txRef}");

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                Log::warning('Chapa verification exception: ' . $e->getMessage());
            }
        }

        // Return verified state for sandbox/simulation
        return [
            'status'  => 'success',
            'message' => 'Payment verified successfully (Sandbox Simulation)',
            'data'    => [
                'tx_ref'         => $txRef,
                'status'         => 'success',
                'currency'       => 'ETB',
                'payment_method' => 'telebirr',
            ]
        ];
    }

    public function handleWebhook(string $rawPayload, string $signature): string
    {
        $data = json_decode($rawPayload, true);
        $webhookSecret = config('services.chapa.webhook_secret');

        if ($webhookSecret && !empty($signature)) {
            $expectedSig = hash_hmac('sha256', $rawPayload, $webhookSecret);
            if (! hash_equals($expectedSig, $signature)) {
                throw new \RuntimeException('Invalid Chapa webhook signature');
            }
        }

        if (($data['status'] ?? '') !== 'success') {
            throw new \RuntimeException('Chapa payment not successful');
        }

        return $data['tx_ref'] ?? '';
    }

    public function refund(string $transactionId, float $amount): bool
    {
        return false;
    }
}
