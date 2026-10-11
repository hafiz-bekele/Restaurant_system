<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.'.$this->order->ulid),
            new Channel('kitchen'),
            new Channel('admin'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'ulid' => $this->order->ulid,
            'status' => $this->order->status->value,
            'label' => $this->order->status->label(),
        ];
    }
}
