<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'status'        => ['sometimes', 'in:active,inactive,blocked'],
            'role'          => ['sometimes', 'string', 'exists:roles,name'],
            'password'      => ['sometimes', 'string', 'min:8'],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'on_duty'       => ['sometimes', 'boolean'],
            'section'       => ['nullable', 'string', 'max:50'],
        ];
    }
}
