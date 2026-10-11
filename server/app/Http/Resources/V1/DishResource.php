<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DishResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'image' => $this->image,
            'calories' => $this->calories,
            'prep_time' => $this->prep_time,
            'is_vegetarian' => $this->is_vegetarian,
            'is_vegan' => $this->is_vegan,
            'spice_level' => $this->spice_level,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'avg_rating' => (float) ($this->avg_rating ?? 0),
            'orders_count' => $this->orders_count,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => $this->whenLoaded('images', fn () => $this->images->pluck('path')),
            'variants' => $this->whenLoaded('variants'),
            'addon_groups' => $this->whenLoaded('addonGroups'),
            'allergens' => $this->whenLoaded('allergens'),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'reviews' => $this->whenLoaded('reviews'),
        ];
    }
}
