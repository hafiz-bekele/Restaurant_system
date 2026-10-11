<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'area'     => ['nullable', 'string', 'max:50'],
            'status'   => ['sometimes', 'in:available,occupied,reserved,inactive'],
        ];
    }
}
