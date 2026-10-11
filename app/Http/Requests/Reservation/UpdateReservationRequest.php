<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reserved_at' => ['nullable', 'date', 'after:now'],
            'party_size'  => ['nullable', 'integer', 'min:1', 'max:20'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
