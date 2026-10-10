<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = [
        'name', 'polygon', 'radius_km', 'fee', 'min_order', 'eta_minutes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'polygon' => 'array',
            'fee' => 'decimal:2',
            'min_order' => 'decimal:2',
            'radius_km' => 'decimal:2',
            'is_active' => 'boolean',
            'eta_minutes' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
