<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'table_id'    => ['required', 'integer', 'exists:tables,id'],
            'name'        => ['required', 'string', 'max:255'],
            'phone'       => ['required', 'string', 'max:20'],
            'email'       => ['required', 'email', 'max:255'],
            'party_size'  => ['required', 'integer', 'min:1', 'max:20'],
            'reserved_at' => ['required', 'date', 'after:now'],
            'duration'    => ['nullable', 'integer', 'min:30', 'max:300'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
