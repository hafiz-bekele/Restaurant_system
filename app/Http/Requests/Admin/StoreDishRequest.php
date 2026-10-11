<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id'    => ['required', 'integer', 'exists:categories,id'],
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'price'          => ['required', 'numeric', 'min:0'],
            'image'          => ['nullable', 'string'],
            'calories'       => ['nullable', 'integer'],
            'prep_time'      => ['nullable', 'integer'],
            'is_vegetarian'  => ['boolean'],
            'is_vegan'       => ['boolean'],
            'spice_level'    => ['integer', 'between:0,3'],
            'is_available'   => ['boolean'],
            'is_featured'    => ['boolean'],
        ];
    }
}
