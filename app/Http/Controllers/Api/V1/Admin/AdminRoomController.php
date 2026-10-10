<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminRoomController extends Controller
{
    public function index(): JsonResponse
    {
        $rooms = Room::withoutGlobalScopes()
            ->where('hotel_id', app('current_hotel_id'))
            ->orderBy('room_number')
            ->get();

        return $this->success($rooms);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_number'    => 'required|string|max:20',
            'type'           => 'required|in:standard,deluxe,suite,twin,single',
            'capacity'       => 'required|integer|min:1|max:10',
            'price_per_night'=> 'required|numeric|min:0',
            'amenities'      => 'nullable|array',
            'is_active'      => 'boolean',
        ]);

        $data['hotel_id']  = app('current_hotel_id');
        $data['qr_token']  = Str::random(32);

        return $this->success(Room::create($data), 'Room created', 201);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $data = $request->validate([
            'room_number'    => 'sometimes|string|max:20',
            'type'           => 'sometimes|in:standard,deluxe,suite,twin,single',
            'capacity'       => 'sometimes|integer|min:1|max:10',
            'price_per_night'=> 'sometimes|numeric|min:0',
            'status'         => 'sometimes|in:available,occupied,maintenance',
            'is_active'      => 'boolean',
        ]);

        $room->update($data);

        return $this->success($room->fresh(), 'Room updated');
    }

    public function destroy(Room $room): JsonResponse
    {
        $room->delete();

        return $this->success(null, 'Room deleted');
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = RoomBooking::with('room:id,room_number,type')
            ->when(app('current_hotel_id'), fn ($q) => $q->where('hotel_id', app('current_hotel_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25);

        return $this->paginated($bookings);
    }

    public function updateBooking(Request $request, RoomBooking $roomBooking): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,checked_in,checked_out,cancelled',
            'notes'  => 'nullable|string|max:500',
        ]);

        $roomBooking->update($data);

        return $this->success($roomBooking->fresh(), 'Booking updated');
    }
}
