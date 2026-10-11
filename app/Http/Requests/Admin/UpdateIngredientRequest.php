<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:100'],
            'unit'          => ['sometimes', 'string', 'max:20'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
            'cost'          => ['sometimes', 'numeric', 'min:0'],
            'supplier_id'   => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }
}
