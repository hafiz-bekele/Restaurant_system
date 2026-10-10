<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DishImage extends Model
{
    protected $fillable = ['dish_id', 'path', 'sort_order'];

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
