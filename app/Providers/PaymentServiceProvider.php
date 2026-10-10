<?php

namespace App\Providers;

use App\Contracts\Payment\PaymentGateway;
use App\Services\Payment\ChapaGateway;
use App\Services\Payment\StripeGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function () {
            return match (config('services.payment.default', 'stripe')) {
                'chapa' => new ChapaGateway,
                default => new StripeGateway,
            };
        });

        $this->app->bind('payment.stripe', fn () => new StripeGateway);
        $this->app->bind('payment.chapa', fn () => new ChapaGateway);
    }
}
