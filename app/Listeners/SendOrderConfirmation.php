<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order;

        if (! $order->user) {
            return;
        }

        // TODO: replace with a proper Mailable / Notification once email templates are designed.
        // Placeholder logs the intent so the listener is wired and functional.
        Log::info('Order confirmation queued', [
            'order_ulid' => $order->ulid,
            'user_email' => $order->user->email,
            'total'      => $order->total,
        ]);
    }
}
