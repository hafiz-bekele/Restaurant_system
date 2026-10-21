<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationPricing extends Model
{
    protected $table = 'reservation_pricing';

    protected $fillable = [
        'duration_hours',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
            'price'          => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }
}
