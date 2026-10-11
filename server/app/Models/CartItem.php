<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'dish_id', 'variant_id', 'quantity', 'options', 'note'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'quantity' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(DishVariant::class, 'variant_id');
    }
}
