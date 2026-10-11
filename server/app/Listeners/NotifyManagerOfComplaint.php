<?php

namespace App\Listeners;

use App\Events\ComplaintSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * REQ-12.1: Alert manager when a guest submits a complaint ticket.
 */
class NotifyManagerOfComplaint implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(ComplaintSubmitted $event): void
    {
        $ticket = $event->ticket;

        // TODO: replace with a proper Notification (mail + push) once templates are designed.
        Log::info('Manager complaint alert queued', [
            'ticket_number' => $ticket->ticket_number,
            'category'      => $ticket->category,
            'hotel_id'      => $ticket->hotel_id,
        ]);
    }
}
