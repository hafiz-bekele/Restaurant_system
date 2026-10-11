<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'          => ['sometimes', 'in:open,investigating,resolved,dismissed'],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
            'assigned_to'     => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
