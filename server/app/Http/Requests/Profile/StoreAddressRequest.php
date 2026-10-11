<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label'      => ['required', 'string', 'max:50'],
            'line1'      => ['required', 'string', 'max:255'],
            'line2'      => ['nullable', 'string', 'max:255'],
            'city'       => ['required', 'string', 'max:100'],
            'lat'        => ['nullable', 'numeric'],
            'lng'        => ['nullable', 'numeric'],
            'is_default' => ['boolean'],
        ];
    }
}
