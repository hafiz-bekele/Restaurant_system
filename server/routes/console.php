<?php

use App\Services\ReservationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reservations:expire', function (ReservationService $service) {
    $res = $service->expireOverdueReservations();
    $this->info("Expired {$res['expired_reservations']} completed reservations and released {$res['cancelled_holds']} timed-out payment holds.");
})->purpose('Expire finished reservations and release expired 10-minute payment holds');

Schedule::command('reservations:expire')->everyMinute();
