<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Dish extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'name_am',
        'slug',
        'description',
        'description_am',
        'price',
        'image',
        'calories',
        'prep_time',
        'is_vegetarian',
        'is_vegan',
        'spice_level',
        'is_available',
        'is_featured',
        'avg_rating',
        'orders_count',
        'sort_order',
        'available_from',
        'available_until',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'avg_rating' => 'decimal:2',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Dish $dish) {
            if (empty($dish->slug)) {
                $base = \Illuminate\Support\Str::slug($dish->name);
                $dish->slug = ($base ?: 'dish') . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
            }
        });
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_am' => $this->name_am,
            'description' => $this->description,
            'description_am' => $this->description_am,
            'category' => $this->relationLoaded('category') ? $this->category?->name : null,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(DishImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(DishVariant::class);
    }

    public function addonGroups(): HasMany
    {
        return $this->hasMany(AddonGroup::class);
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'dish_allergen');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'dish_ingredient')
            ->withPivot('quantity');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->where('is_available', true);
    }
}
