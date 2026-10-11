<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hotel_id'       => ['required', 'integer', 'exists:hotels,id'],
            'room_id'        => ['required', 'integer', 'exists:rooms,id'],
            'guest_name'     => ['required', 'string', 'max:255'],
            'guest_email'    => ['required', 'email', 'max:255'],
            'guest_phone'    => ['required', 'string', 'max:30'],
            'check_in_date'  => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }
}
