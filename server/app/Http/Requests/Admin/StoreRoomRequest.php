<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'room_number'     => ['required', 'string', 'max:20'],
            'type'            => ['required', 'in:standard,deluxe,suite,twin,single'],
            'capacity'        => ['required', 'integer', 'min:1', 'max:10'],
            'price_per_night' => ['required', 'numeric', 'min:0'],
            'amenities'       => ['nullable', 'array'],
            'is_active'       => ['boolean'],
        ];
    }
}
