<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'password'      => ['required', 'string', 'min:8'],
            'role'          => ['required', 'string', 'exists:roles,name'],
            'section'       => ['nullable', 'string', 'max:100'],
            'on_duty'       => ['nullable', 'boolean'],
            'hotel_id'      => ['nullable', 'integer', 'exists:hotels,id'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
