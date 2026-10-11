<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:100'],
            'fee'         => ['sometimes', 'numeric', 'min:0'],
            'min_order'   => ['nullable', 'numeric', 'min:0'],
            'eta_minutes' => ['nullable', 'integer', 'min:1'],
            'radius_km'   => ['nullable', 'numeric', 'min:0'],
            'is_active'   => ['boolean'],
        ];
    }
}
