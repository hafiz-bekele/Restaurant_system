<?php

namespace App\Http\Controllers\Api\V1\Reservation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\AvailabilityRequest;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Requests\Reservation\UpdateReservationRequest;
use App\Http\Resources\V1\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    public function availability(AvailabilityRequest $request): JsonResponse
    {
        $tables = $this->reservationService->getAvailableTables(
            Carbon::parse($request->validated()['date']),
            $request->validated()['party_size'],
            $request->validated()['duration'] ?? 90
        );

        return $this->success($tables);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $data             = $request->validated();
        $data['user_id']  = $request->user()?->id;
        $reservation      = $this->reservationService->create($data);

        return $this->success(
            new ReservationResource($reservation->load('table')),
            'Reservation created',
            201
        );
    }

    public function show(Reservation $reservation): JsonResponse
    {
        return $this->success(new ReservationResource($reservation->load('table')));
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $reservation->update($request->validated());

        return $this->success(new ReservationResource($reservation->fresh('table')));
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        $this->reservationService->cancel($reservation);

        return $this->success(null, 'Reservation cancelled');
    }
}
