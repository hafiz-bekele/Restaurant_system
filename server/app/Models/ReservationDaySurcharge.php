<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationDaySurcharge extends Model
{
    protected $table = 'reservation_day_surcharges';

    protected $fillable = [
        'days_ahead',
        'percent',
    ];

    protected function casts(): array
    {
        return [
            'days_ahead' => 'integer',
            'percent'    => 'decimal:2',
        ];
    }
}
