<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title'      => ['sometimes', 'string', 'max:255'],
            'subtitle'   => ['nullable', 'string', 'max:255'],
            'image'      => $this->hasFile('image')
                ? ['sometimes', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif,svg', 'max:10240']
                : ['sometimes', 'string', 'max:2000'],
            'link'       => ['nullable', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
            'starts_at'  => ['nullable', 'date'],
            'ends_at'    => ['nullable', 'date'],
        ];
    }
}
