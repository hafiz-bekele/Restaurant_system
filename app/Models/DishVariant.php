<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DishVariant extends Model
{
    protected $fillable = ['dish_id', 'name', 'price_delta', 'is_available'];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
