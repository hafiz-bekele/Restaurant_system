<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'      => ['required', 'in:pending,confirmed,cancelled,completed,no_show'],
            'table_id'    => ['nullable', 'integer', 'exists:tables,id'],
            'reserved_at' => ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
