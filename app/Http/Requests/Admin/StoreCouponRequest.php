<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code'      => ['nullable', 'string', 'max:50', 'unique:coupons,code'],
            'type'      => ['required', 'in:percent,fixed,free_delivery'],
            'value'     => ['required', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'max_uses'  => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];
    }
}
