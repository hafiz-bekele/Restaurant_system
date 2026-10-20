<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reservations:auto-redeem', function (\App\Services\ReservationService $service) {
    $count = $service->autoRedeemExpiredReservations();
    $this->info("Auto-redeemed {$count} expired reservations (>= 2 hours old).");
})->purpose('Automatically redeem reservations exceeding the 2-hour duration policy');
