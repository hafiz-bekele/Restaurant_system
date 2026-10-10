<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\ComplaintTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REQ-11.2: Guest complaint submission with ticket generation.
 */
class ComplaintController extends Controller
{
    /**
     * Guest submits a complaint — generates TCK-XXXX and returns ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hotel_id'    => 'required|integer|exists:hotels,id',
            'order_id'    => 'nullable|integer|exists:orders,id',
            'category'    => 'required|in:late_food,cold_dish,missing_item,staff_behavior,other',
            'subject'     => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $data['user_id'] = $request->user()?->id;

        $ticket = ComplaintTicket::create($data);

        // REQ-12.1: broadcast urgent alert to manager (event will be listened by NotifyManagerOfComplaint)
        event(new \App\Events\ComplaintSubmitted($ticket));

        return $this->success([
            'ticket_number' => $ticket->ticket_number,
            'status'        => $ticket->status,
            'created_at'    => $ticket->created_at,
        ], 'Complaint submitted. Ticket: '.$ticket->ticket_number, 201);
    }

    /**
     * Guest looks up their ticket status.
     */
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
