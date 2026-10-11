<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id'  => ['sometimes', 'integer', 'exists:categories,id'],
            'name'         => ['sometimes', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'image'        => ['nullable', 'string'],
            'is_available' => ['boolean'],
            'is_featured'  => ['boolean'],
            'spice_level'  => ['integer', 'between:0,3'],
            'calories'     => ['nullable', 'integer'],
            'prep_time'    => ['nullable', 'integer'],
        ];
    }
}
