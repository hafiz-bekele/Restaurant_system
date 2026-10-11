<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:100'],
            'unit'          => ['required', 'string', 'max:20'],
            'stock'         => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'cost'          => ['required', 'numeric', 'min:0'],
            'supplier_id'   => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }
}
