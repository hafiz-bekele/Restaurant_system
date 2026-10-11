<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Models\Room;
use Illuminate\Http\JsonResponse;

/**
 * REQ-3.1 / REQ-3.2: Resolve a QR token and return full hotel + context payload.
 */
class GuestQrController extends Controller
{
    public function resolve(string $token): JsonResponse
    {
        // Try table QR first
        $table = RestaurantTable::withoutGlobalScopes()
            ->where('qr_token', $token)
            ->with('hotel:id,name,logo,currency,vat_rate,service_charge_rate')
            ->first();

        if ($table) {
            return $this->success([
                'type'       => 'table',
                'hotel_id'   => $table->hotel_id,
                'hotel_name' => $table->hotel?->name,
                'logo_url'   => $table->hotel?->logo ? asset('storage/'.$table->hotel->logo) : null,
                'currency'   => $table->hotel?->currency ?? 'ETB',
                'table'      => [
                    'id'       => $table->id,
                    'name'     => $table->name,
                    'capacity' => $table->capacity,
                    'area'     => $table->area,
                    'status'   => $table->status instanceof \App\Enums\TableStatus
                        ? $table->status->value
                        : $table->status,
                ],
            ]);
        }

        // Try room QR
        $room = Room::withoutGlobalScopes()
            ->where('qr_token', $token)
            ->with('hotel:id,name,logo,currency,vat_rate,service_charge_rate')
            ->first();

        if ($room) {
            $activeBooking = $room->activeBooking();

            // REQ-3.3: if no active booking, direct guest to booking flow
            if (! $activeBooking) {
                return $this->success([
                    'type'        => 'room',
                    'hotel_id'    => $room->hotel_id,
                    'hotel_name'  => $room->hotel?->name,
                    'logo_url'    => $room->hotel?->logo ? asset('storage/'.$room->hotel->logo) : null,
                    'currency'    => $room->hotel?->currency ?? 'ETB',
                    'room_number' => $room->room_number,
                    'reservation' => null,
                    'action'      => 'book_room',
                ]);
            }

            return $this->success([
                'type'        => 'room',
                'hotel_id'    => $room->hotel_id,
                'hotel_name'  => $room->hotel?->name,
                'logo_url'    => $room->hotel?->logo ? asset('storage/'.$room->hotel->logo) : null,
                'currency'    => $room->hotel?->currency ?? 'ETB',
                'room_number' => $room->room_number,
                'reservation' => [
                    'id'             => $activeBooking->id,
                    'guest_name'     => $activeBooking->guest_name,
                    'check_in_date'  => $activeBooking->check_in_date,
                    'check_out_date' => $activeBooking->check_out_date,
                    'status'         => $activeBooking->status,
                ],
                'action' => 'order',
            ]);
        }

        return $this->error('QR code not found or expired.', 404);
    }
}
