<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
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
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'room_number'     => ['sometimes', 'string', 'max:20'],
            'type'            => ['sometimes', 'in:standard,deluxe,suite,twin,single'],
            'capacity'        => ['sometimes', 'integer', 'min:1', 'max:10'],
            'price_per_night' => ['sometimes', 'numeric', 'min:0'],
            'status'          => ['sometimes', 'in:available,occupied,maintenance'],
            'amenities'       => ['nullable', 'array'],
            'description'     => ['nullable', 'string'],
            'image'           => $this->hasFile('image')
                ? ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif,svg', 'max:10240']
                : ['nullable', 'string', 'max:2000'],
            'image_url'       => ['nullable', 'string', 'max:2000'],
            'is_active'       => ['boolean'],
        ];
    }
}
