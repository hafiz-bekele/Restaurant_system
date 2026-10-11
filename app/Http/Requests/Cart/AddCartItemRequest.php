<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dish_id'    => ['required', 'integer', 'exists:dishes,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:20'],
            'variant_id' => ['nullable', 'integer', 'exists:dish_variants,id'],
            'options'    => ['nullable', 'array'],
            'note'       => ['nullable', 'string', 'max:255'],
        ];
    }
}
