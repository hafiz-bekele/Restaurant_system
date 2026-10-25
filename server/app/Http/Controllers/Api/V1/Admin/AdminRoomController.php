<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Requests\Admin\UpdateRoomRequest;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminRoomController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(
            Room::withoutGlobalScopes()
                ->where('hotel_id', app('current_hotel_id'))
                ->orderBy('room_number')
                ->get()
        );
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $data             = $request->validated();
        $data['hotel_id'] = app('current_hotel_id');
        $data['qr_token'] = Str::random(32);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('rooms', 'public');
            $data['image'] = Storage::disk('public')->url($path);
        } elseif (!empty($data['image_url']) && empty($data['image'])) {
            $data['image'] = $data['image_url'];
        }
        unset($data['image_url']);

        return $this->success(Room::create($data), 'Room created', 201);
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($room->image && Str::contains($room->image, '/storage/rooms/')) {
                $oldPath = Str::after($room->image, '/storage/');
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('rooms', 'public');
            $data['image'] = Storage::disk('public')->url($path);
        } elseif (!empty($data['image_url']) && empty($data['image'])) {
            $data['image'] = $data['image_url'];
        }
        unset($data['image_url']);

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
            'status' => ['required', 'in:pending,confirmed,checked_in,checked_out,cancelled'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $roomBooking->update($data);

        return $this->success($roomBooking->fresh(), 'Booking updated');
    }
}
