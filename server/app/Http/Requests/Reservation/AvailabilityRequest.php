<?php

namespace App\Http\Requests\Reservation;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxAdvanceDate = Carbon::now()->addDays(14)->toDateString();

        return [
            'date'           => [
                'required',
                'date',
                'after_or_equal:today',
                'before_or_equal:' . $maxAdvanceDate,
            ],
            'start_time'     => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'duration_hours' => ['required', 'numeric', 'in:1,2,3,4,6,12,18,24'],
            'party_size'     => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('date') && $this->filled('start_time')) {
                try {
                    $dateStr    = Carbon::parse($this->date)->toDateString();
                    $timeStr    = $this->start_time;
                    $combinedAt = Carbon::parse("{$dateStr} {$timeStr}");

                    if ($combinedAt->isToday() && $combinedAt->isBefore(Carbon::now()->addMinutes(14))) {
                        $validator->errors()->add('start_time', 'Start time must be at least 15 minutes from now for same-day reservations.');
                    }
                } catch (\Throwable) {
                    $validator->errors()->add('start_time', 'Invalid date and time format.');
                }
            }
        });
    }

    public function getStartAt(): Carbon
    {
        $dateStr = Carbon::parse($this->date)->toDateString();
        return Carbon::parse("{$dateStr} {$this->start_time}");
    }
}
