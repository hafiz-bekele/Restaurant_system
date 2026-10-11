<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date'       => ['required', 'date', 'after_or_equal:today'],
            'party_size' => ['required', 'integer', 'min:1', 'max:20'],
            'duration'   => ['nullable', 'integer', 'min:30', 'max:300'],
        ];
    }
}
