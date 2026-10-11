<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\AvailableRoomsRequest;
use App\Http\Requests\Guest\StoreRoomBookingRequest;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Http\JsonResponse;

class RoomBookingController extends Controller
{
    public function available(AvailableRoomsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $bookedRoomIds = RoomBooking::where('hotel_id', $data['hotel_id'])
            ->whereNotIn('status', ['cancelled'])
            ->where('check_in_date', '<', $data['check_out_date'])
            ->where('check_out_date', '>', $data['check_in_date'])
            ->pluck('room_id');

        $rooms = Room::withoutGlobalScopes()
            ->where('hotel_id', $data['hotel_id'])
            ->where('is_active', true)
            ->where('status', 'available')
            ->whereNotIn('id', $bookedRoomIds)
            ->when(isset($data['capacity']), fn ($q) => $q->where('capacity', '>=', $data['capacity']))
            ->when(isset($data['type']), fn ($q) => $q->where('type', $data['type']))
            ->get();

        return $this->success($rooms);
    }

    public function store(StoreRoomBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (RoomBooking::hasOverlap($data['room_id'], $data['check_in_date'], $data['check_out_date'])) {
            return $this->error('Room is not available for the selected dates.', 422);
        }

        $room              = Room::withoutGlobalScopes()->findOrFail($data['room_id']);
        $nights            = (int) now()->parse($data['check_in_date'])->diffInDays($data['check_out_date']);
        $data['total_amount'] = $room->price_per_night * $nights;
        $data['status']    = 'pending';

        $booking = RoomBooking::create($data);

        return $this->success($booking->load('room'), 'Booking created successfully', 201);
    }

    public function show(RoomBooking $roomBooking): JsonResponse
    {
        return $this->success($roomBooking->load('room.hotel'));
    }
}
