<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coupon_code' => $this->coupon_code,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'dish' => new DishResource($item->dish),
                'variant' => $item->variant,
                'quantity' => $item->quantity,
                'options' => $item->options,
                'note' => $item->note,
                'line_total' => round(
                    ((float) $item->dish->price + (float) ($item->variant?->price_delta ?? 0) + collect($item->options ?? [])->sum('price')) * $item->quantity,
                    2
                ),
            ])
            ),
        ];
    }
}
