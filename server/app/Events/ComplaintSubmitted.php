<?php

namespace App\Events;

use App\Models\ComplaintTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * REQ-11.2 / REQ-12.1: Broadcast urgent alert to manager when a guest submits a complaint.
 */
class ComplaintSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ComplaintTicket $ticket) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('admin'),
            new Channel('hotel.'.$this->ticket->hotel_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_number' => $this->ticket->ticket_number,
            'category'      => $this->ticket->category,
            'subject'       => $this->ticket->subject,
            'hotel_id'      => $this->ticket->hotel_id,
        ];
    }
}
