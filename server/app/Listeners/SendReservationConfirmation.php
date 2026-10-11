<?php

namespace App\Listeners;

use App\Events\ReservationCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendReservationConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(ReservationCreated $event): void
    {
        $reservation = $event->reservation;

        // TODO: replace with a proper Mailable / Notification once email templates are designed.
        Log::info('Reservation confirmation queued', [
            'reservation_id' => $reservation->id,
            'guest_email'    => $reservation->email,
            'reserved_at'    => $reservation->reserved_at,
            'party_size'     => $reservation->party_size,
        ]);
    }
}
