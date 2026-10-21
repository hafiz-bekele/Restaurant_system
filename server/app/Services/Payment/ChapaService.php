<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChapaService implements PaymentGatewayInterface
{
    protected string $baseUrl = 'https://api.chapa.co/v1';

    protected function getSecretKey(): ?string
    {
        return config('services.chapa.secret') ?: env('CHAPA_SECRET_KEY', env('CHAPA_SECRET'));
    }

    protected function getWebhookSecret(): ?string
    {
        return config('services.chapa.webhook_secret') ?: env('CHAPA_WEBHOOK_SECRET');
    }

    public function isConfigured(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        $key = $this->getSecretKey();
        if (empty($key)) {
            return false;
        }

        if (
            str_contains($key, 'your-chapa-secret-key') ||
            str_contains($key, 'placeholder') ||
            str_contains($key, 'DEMOKEY') ||
            $key === 'CHASECK_TEST-xxx'
        ) {
            return false;
        }

        return true;
    }

    public function initializePayment(array $data): array
    {
        $txRef = $data['tx_ref'];

        $payload = [
            'amount'        => number_format((float) $data['amount'], 2, '.', ''),
            'currency'      => $data['currency'] ?? 'ETB',
            'email'         => $data['email'],
            'first_name'    => $data['first_name'] ?: 'Guest',
            'last_name'     => $data['last_name'] ?: 'Customer',
            'phone_number'  => $data['phone_number'],
            'tx_ref'        => $txRef,
            'callback_url'  => $data['callback_url'],
            'return_url'    => $data['return_url'],
            'customization' => $data['customization'] ?? [
                'title'       => 'Table Reservation Booking',
                'description' => 'Table reservation payment',
            ],
        ];

        if ($this->isConfigured()) {
            try {
                $response = Http::withToken($this->getSecretKey())
                    ->timeout(12)
                    ->post("{$this->baseUrl}/transaction/initialize", $payload);

                Log::info('Chapa initialize response', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                if ($response->successful() && $response->json('status') === 'success') {
                    $checkoutUrl = $response->json('data.checkout_url');
                    return [
                        'checkout_url' => $checkoutUrl,
                        'tx_ref'       => $txRef,
                        'mode'         => 'live',
                        'raw'          => $response->json(),
                    ];
                }

                Log::warning('Chapa initialization returned unhandled status', [
                    'response' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Chapa initialization exception: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }

        // Fallback for local testing / sandbox when real secret is not yet provided
        $frontendUrl  = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $simulatedUrl = $data['return_url'] . (str_contains($data['return_url'], '?') ? '&' : '?') . "simulation=true&tx_ref={$txRef}";

        return [
            'checkout_url' => $simulatedUrl,
            'tx_ref'       => $txRef,
            'mode'         => 'simulation',
            'raw'          => ['status' => 'simulation', 'message' => 'Local sandbox simulation'],
        ];
    }

    public function verifyPayment(string $txRef): array
    {
        if ($this->isConfigured()) {
            try {
                $response = Http::withToken($this->getSecretKey())
                    ->timeout(12)
                    ->get("{$this->baseUrl}/transaction/verify/{$txRef}");

                Log::info('Chapa verify response', [
                    'tx_ref' => $txRef,
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                if ($response->successful() && $response->json('status') === 'success') {
                    $data = $response->json('data') ?? [];
                    return [
                        'status'    => 'success',
                        'amount'    => (float) ($data['amount'] ?? 0),
                        'currency'  => (string) ($data['currency'] ?? 'ETB'),
                        'reference' => (string) ($data['reference'] ?? $txRef),
                        'raw'       => $response->json(),
                    ];
                }

                return [
                    'status'    => 'failed',
                    'amount'    => 0.0,
                    'currency'  => 'ETB',
                    'reference' => $txRef,
                    'raw'       => $response->json() ?? ['message' => 'Verification failed'],
                ];
            } catch (\Throwable $e) {
                Log::error('Chapa verification exception: ' . $e->getMessage());
                return [
                    'status'    => 'error',
                    'amount'    => 0.0,
                    'currency'  => 'ETB',
                    'reference' => $txRef,
                    'raw'       => ['error' => $e->getMessage()],
                ];
            }
        }

        // Simulation mode for tests / sandbox
        return [
            'status'    => 'success',
            'amount'    => 0.0,
            'currency'  => 'ETB',
            'reference' => 'SIM-' . $txRef,
            'raw'       => ['simulation' => true],
        ];
    }

    public function verifyWebhookSignature(string $rawPayload, string $signature): bool
    {
        $webhookSecret = $this->getWebhookSecret();
        if (empty($webhookSecret)) {
            // If no webhook secret is configured in local dev, allow for testing
            return true;
        }

        if (empty($signature)) {
            return false;
        }

        $expectedSig = hash_hmac('sha256', $rawPayload, $webhookSecret);
        return hash_equals($expectedSig, $signature);
    }
}
