<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:confirmed,preparing,ready,out_for_delivery,delivered,cancelled'],
            'note'   => ['nullable', 'string', 'max:255'],
        ];
    }
}
