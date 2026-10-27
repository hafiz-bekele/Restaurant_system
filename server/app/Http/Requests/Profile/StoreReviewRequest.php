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
            'dish_id'   => ['nullable', 'integer', 'exists:dishes,id', 'required_without:room_id'],
            'room_id'   => ['nullable', 'integer', 'exists:rooms,id', 'required_without:dish_id'],
            'order_id'  => ['nullable', 'integer', 'exists:orders,id'],
            'rating'    => ['required', 'integer', 'between:1,5'],
            'title'     => ['nullable', 'string', 'max:150'],
            'comment'   => ['nullable', 'string', 'max:2000'],
            'images'    => ['nullable', 'array', 'max:5'],
            'images.*'  => ['string', 'max:500'],
        ];
    }
}
