<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ComplaintTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REQ-11.2: Manager views and manages complaint tickets.
 */
class AdminComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = ComplaintTicket::with(['order:id,ulid', 'user:id,name,email'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->latest()
            ->paginate(25);

        return $this->paginated($tickets);
    }

    public function show(ComplaintTicket $complaintTicket): JsonResponse
    {
        return $this->success($complaintTicket->load(['order:id,ulid', 'user:id,name,email', 'assignee:id,name']));
    }

    public function update(Request $request, ComplaintTicket $complaintTicket): JsonResponse
    {
        $data = $request->validate([
            'status'          => 'sometimes|in:open,investigating,resolved,dismissed',
            'resolution_note' => 'nullable|string|max:1000',
            'assigned_to'     => 'nullable|integer|exists:users,id',
        ]);

        if (isset($data['status']) && $data['status'] === 'resolved') {
            $data['resolved_at'] = now();
        }

        $complaintTicket->update($data);
        ActivityLog::record('complaint.updated', $complaintTicket, ['ticket' => $complaintTicket->ticket_number, 'status' => $data['status'] ?? null]);

        return $this->success($complaintTicket->fresh(), 'Ticket updated');
    }
}
