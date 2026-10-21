<?php

namespace App\Providers;

use App\Events\ComplaintSubmitted;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\ReservationCreated;
use App\Listeners\NotifyCustomerOfStatusChange;
use App\Listeners\NotifyManagerOfComplaint;
use App\Listeners\SendOrderConfirmation;
use App\Listeners\SendReservationConfirmation;
use App\Models\Order;
use App\Models\Reservation;
use App\Policies\OrderPolicy;
use App\Policies\ReservationPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Default binding so TenantScope never throws "not bound" error
        app()->bind('current_hotel_id', fn () => null);

        // Chapa Payment Gateway service binding
        $this->app->bind(
            \App\Contracts\Payment\PaymentGatewayInterface::class,
            \App\Services\Payment\ChapaService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // Implicitly grant 'super-admin' role all permission checks using Gate::before
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Policies
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);

        // Events → Listeners
        Event::listen(OrderPlaced::class, SendOrderConfirmation::class);
        Event::listen(OrderStatusChanged::class, NotifyCustomerOfStatusChange::class);
        Event::listen(ReservationCreated::class, SendReservationConfirmation::class);
        Event::listen(ComplaintSubmitted::class, NotifyManagerOfComplaint::class);
    }
}
