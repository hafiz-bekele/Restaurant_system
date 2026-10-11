<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dish_id'  => ['required', 'integer', 'exists:dishes,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'rating'   => ['required', 'integer', 'between:1,5'],
            'comment'  => ['nullable', 'string', 'max:1000'],
        ];
    }
}
