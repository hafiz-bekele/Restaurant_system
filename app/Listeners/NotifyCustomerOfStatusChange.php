<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyCustomerOfStatusChange implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;

        if (! $order->user) {
            return;
        }

        // TODO: replace with a proper Notification (mail + push) once templates are designed.
        // The broadcast in OrderStatusChanged already pushes to private-channel; this handles
        // email / SMS / push-notification delivery out-of-band.
        Log::info('Order status change notification queued', [
            'order_ulid' => $order->ulid,
            'status'     => $order->status->value,
            'user_email' => $order->user->email,
        ]);
    }
}
