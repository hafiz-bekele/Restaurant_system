<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('image_url') && !$this->hasFile('image') && !$this->filled('image')) {
            $this->merge(['image' => $this->input('image_url')]);
        }
        if ($this->has('is_available')) {
            $this->merge(['is_available' => filter_var($this->is_available, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true]);
        }
        if ($this->has('is_featured')) {
            $this->merge(['is_featured' => filter_var($this->is_featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id'  => ['sometimes', 'integer', 'exists:categories,id'],
            'name'         => ['sometimes', 'string', 'max:255'],
            'name_am'      => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'description_am' => ['nullable', 'string'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'image'        => $this->hasFile('image')
                ? ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif,svg', 'max:10240']
                : ['nullable', 'string', 'max:2000'],
            'image_url'    => ['nullable', 'string', 'max:2000'],
            'is_available' => ['boolean'],
            'is_featured'  => ['boolean'],
            'spice_level'  => ['integer', 'between:0,3'],
            'calories'     => ['nullable', 'integer'],
            'prep_time'    => ['nullable', 'integer'],
        ];
    }
}
