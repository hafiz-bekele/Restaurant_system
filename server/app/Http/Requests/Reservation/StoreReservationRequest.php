<?php

namespace App\Http\Requests\Reservation;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $cleaned = preg_replace('/[\s\-]/', '', (string) $this->phone);
            $this->merge(['phone' => $cleaned]);
        }
    }

    public function rules(): array
    {
        $maxAdvanceDate = Carbon::now()->addDays(7)->toDateString();

        return [
            'customer_name'    => ['sometimes', 'required', 'string', 'max:100'],
            'name'             => ['sometimes', 'required', 'string', 'max:100'],
            'email'            => ['required', 'email', 'max:255'],
            'phone'            => [
                'required',
                'string',
                'regex:/^(\+251\s?[79]\d{8}|0[79]\d{8})$/',
            ],
            'party_size'       => ['required', 'integer', 'min:1', 'max:20'],
            'table_id'         => ['required', 'integer', 'exists:tables,id'],
            'date'             => ['sometimes', 'required', 'date', 'after_or_equal:today', 'before_or_equal:' . $maxAdvanceDate],
            'start_time'       => ['sometimes', 'required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'start_at'         => ['nullable', 'date'],
            'duration_hours'   => ['required', 'integer', 'in:1,2,3,6,12,18,24'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Determine resolved start_at
            $startAt = null;
            if ($this->filled('start_at')) {
                $startAt = Carbon::parse($this->start_at);
            } elseif ($this->filled('date') && $this->filled('start_time')) {
                try {
                    $dateStr = Carbon::parse($this->date)->toDateString();
                    $startAt = Carbon::parse("{$dateStr} {$this->start_time}");
                } catch (\Throwable) {
                    $validator->errors()->add('start_time', 'Invalid date or time format.');
                }
            } else {
                $validator->errors()->add('date', 'Reservation date and start time are required.');
            }

            if ($startAt) {
                if ($startAt->isToday() && $startAt->isBefore(Carbon::now()->addMinutes(14))) {
                    $validator->errors()->add('start_time', 'Start time must be at least 15 minutes from now for same-day reservations.');
                }
                $this->merge(['start_at' => $startAt->toDateTimeString()]);
            }

            if (! $this->filled('customer_name') && $this->filled('name')) {
                $this->merge(['customer_name' => $this->name]);
            }
        });
    }
}
