<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REQ-4.1 – REQ-4.4: Room browsing and booking for hotel guests.
 */
class RoomBookingController extends Controller
{
    /**
     * REQ-4.1: Browse available rooms with filters.
     */
    public function available(Request $request): JsonResponse
    {
        $request->validate([
            'hotel_id'       => 'required|integer|exists:hotels,id',
            'check_in_date'  => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'capacity'       => 'nullable|integer|min:1',
            'type'           => 'nullable|string',
        ]);

        $bookedRoomIds = RoomBooking::where('hotel_id', $request->hotel_id)
            ->whereNotIn('status', ['cancelled'])
            ->where('check_in_date', '<', $request->check_out_date)
            ->where('check_out_date', '>', $request->check_in_date)
            ->pluck('room_id');

        $rooms = Room::withoutGlobalScopes()
            ->where('hotel_id', $request->hotel_id)
            ->where('is_active', true)
            ->where('status', 'available')
            ->whereNotIn('id', $bookedRoomIds)
            ->when($request->filled('capacity'), fn ($q) => $q->where('capacity', '>=', $request->capacity))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->get();

        return $this->success($rooms);
    }

    /**
     * REQ-4.2 / REQ-4.3: Create a new room booking with overlap check.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hotel_id'       => 'required|integer|exists:hotels,id',
            'room_id'        => 'required|integer|exists:rooms,id',
            'guest_name'     => 'required|string|max:255',
            'guest_email'    => 'required|email|max:255',
            'guest_phone'    => 'required|string|max:30',
            'check_in_date'  => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'notes'          => 'nullable|string|max:500',
        ]);

        // REQ-4.3: prevent overlapping bookings
        if (RoomBooking::hasOverlap($data['room_id'], $data['check_in_date'], $data['check_out_date'])) {
            return $this->error('Room is not available for the selected dates.', 422);
        }

        $room = Room::withoutGlobalScopes()->findOrFail($data['room_id']);
        $nights = (int) now()->parse($data['check_in_date'])->diffInDays($data['check_out_date']);
        $data['total_amount'] = $room->price_per_night * $nights;
        $data['status'] = 'pending';

        $booking = RoomBooking::create($data);

        return $this->success($booking->load('room'), 'Booking created successfully', 201);
    }

    /**
     * Look up a booking by ID (for guest confirmation page).
     */
    public function show(RoomBooking $roomBooking): JsonResponse
    {
        return $this->success($roomBooking->load('room.hotel'));
    }
}
