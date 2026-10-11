<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hotel_id'    => ['required', 'integer', 'exists:hotels,id'],
            'order_id'    => ['nullable', 'integer', 'exists:orders,id'],
            'category'    => ['required', 'in:late_food,cold_dish,missing_item,staff_behavior,other'],
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
