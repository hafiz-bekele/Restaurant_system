<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class WalkInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'table_id'   => ['required', 'integer', 'exists:tables,id'],
            'party_size' => ['required', 'integer', 'min:1', 'max:50'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ];
    }
}
