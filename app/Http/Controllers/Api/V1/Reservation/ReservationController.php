<?php

namespace App\Http\Controllers\Api\V1\Reservation;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'party_size' => 'required|integer|min:1|max:20',
            'duration' => 'nullable|integer|min:30|max:300',
        ]);

        $tables = $this->reservationService->getAvailableTables(
            Carbon::parse($request->date),
            $request->party_size,
            $request->duration ?? 90
        );

        return $this->success($tables);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_id' => 'required|integer|exists:tables,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'party_size' => 'required|integer|min:1|max:20',
            'reserved_at' => 'required|date|after:now',
            'duration' => 'nullable|integer|min:30|max:300',
            'notes' => 'nullable|string|max:500',
        ]);

        $data['user_id'] = $request->user()?->id;
        $reservation = $this->reservationService->create($data);

        return $this->success(new ReservationResource($reservation->load('table')), 'Reservation created', 201);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        return $this->success(new ReservationResource($reservation->load('table')));
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'reserved_at' => 'nullable|date|after:now',
            'party_size' => 'nullable|integer|min:1|max:20',
            'notes' => 'nullable|string|max:500',
        ]);

        $reservation->update($data);

        return $this->success(new ReservationResource($reservation->fresh('table')));
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        $this->reservationService->cancel($reservation);

        return $this->success(null, 'Reservation cancelled');
    }
}
