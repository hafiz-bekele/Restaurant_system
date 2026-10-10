<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddonGroup extends Model
{
    protected $fillable = ['dish_id', 'name', 'required', 'min_select', 'max_select'];

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class);
    }
}
