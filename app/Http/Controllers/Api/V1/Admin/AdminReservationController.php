<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminReservationRequest;
use App\Models\ActivityLog;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reservations = Reservation::with(['user:id,name,email', 'table:id,name,area,capacity'])
            ->when($request->filled('date'), fn ($q) => $q->whereDate('reserved_at', $request->date))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->orderBy('reserved_at')
            ->paginate(25);

        return $this->paginated($reservations->through(fn ($r) => $this->resource($r)));
    }

    public function today(): JsonResponse
    {
        $reservations = Reservation::with(['table:id,name,area,capacity'])
            ->whereDate('reserved_at', today())
            ->whereNotIn('status', [ReservationStatus::Cancelled->value])
            ->orderBy('reserved_at')
            ->get()
            ->map(fn ($r) => $this->resource($r));

        return $this->success($reservations);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        return $this->success($this->resource($reservation->load(['user:id,name,email', 'table'])));
    }

    public function update(UpdateAdminReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validated();
        $reservation->update($data);
        ActivityLog::record('reservation.updated', $reservation, ['status' => $data['status'] ?? null]);

        return $this->success($this->resource($reservation->fresh('table')), 'Reservation updated');
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        $reservation->update(['status' => ReservationStatus::Cancelled]);
        ActivityLog::record('reservation.cancelled', $reservation);

        return $this->success(null, 'Reservation cancelled');
    }

    private function resource(Reservation $r): array
    {
        return [
            'id'          => $r->id,
            'name'        => $r->name,
            'email'       => $r->email,
            'phone'       => $r->phone,
            'party_size'  => $r->party_size,
            'reserved_at' => $r->reserved_at,
            'duration'    => $r->duration,
            'status'      => $r->status instanceof ReservationStatus ? $r->status->value : $r->status,
            'notes'       => $r->notes,
            'table'       => $r->relationLoaded('table') ? $r->table : null,
            'user'        => $r->relationLoaded('user') ? $r->user : null,
            'created_at'  => $r->created_at,
        ];
    }
}
