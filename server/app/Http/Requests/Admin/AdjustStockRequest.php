<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delta'  => ['required', 'numeric'],
            'reason' => ['required', 'in:purchase,waste,adjustment'],
        ];
    }
}
