<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type'             => ['required', 'in:delivery,pickup,dine_in'],
            'payment_method'   => ['required', 'in:stripe,chapa,cash,room_charge'],
            'table_id'         => ['required_if:type,dine_in', 'nullable', 'integer', 'exists:tables,id'],
            'delivery_zone_id' => ['required_if:type,delivery', 'nullable', 'integer', 'exists:delivery_zones,id'],
            'address'          => ['required_if:type,delivery', 'nullable', 'array'],
            'tip'              => ['nullable', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'scheduled_at'     => ['nullable', 'date', 'after:now'],
            'customer_name'    => ['nullable', 'string', 'max:100'],
            'customer_phone'   => ['nullable', 'string', 'max:50'],
            'customer_email'   => ['nullable', 'email', 'max:100'],
        ];
    }
}
