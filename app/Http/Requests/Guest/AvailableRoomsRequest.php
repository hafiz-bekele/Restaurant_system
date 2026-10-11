<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class AvailableRoomsRequest extends FormRequest
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
            'check_in_date'  => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'capacity'       => ['nullable', 'integer', 'min:1'],
            'type'           => ['nullable', 'string'],
        ];
    }
}
