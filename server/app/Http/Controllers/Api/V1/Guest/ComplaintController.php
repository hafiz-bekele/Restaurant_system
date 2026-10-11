<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Events\ComplaintSubmitted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\StoreComplaintRequest;
use App\Models\ComplaintTicket;
use Illuminate\Http\JsonResponse;

class ComplaintController extends Controller
{
    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $data            = $request->validated();
        $data['user_id'] = $request->user()?->id;

        $ticket = ComplaintTicket::create($data);

        event(new ComplaintSubmitted($ticket));

        return $this->success([
            'ticket_number' => $ticket->ticket_number,
            'status'        => $ticket->status,
            'created_at'    => $ticket->created_at,
        ], 'Complaint submitted. Ticket: '.$ticket->ticket_number, 201);
    }

    public function show(string $ticketNumber): JsonResponse
    {
        $ticket = ComplaintTicket::withoutGlobalScopes()
            ->where('ticket_number', $ticketNumber)
            ->firstOrFail();

        return $this->success([
            'ticket_number'   => $ticket->ticket_number,
            'category'        => $ticket->category,
            'subject'         => $ticket->subject,
            'status'          => $ticket->status,
            'resolution_note' => $ticket->resolution_note,
            'created_at'      => $ticket->created_at,
            'resolved_at'     => $ticket->resolved_at,
        ]);
    }
}
