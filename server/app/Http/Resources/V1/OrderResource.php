<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'service_charge' => $this->service_charge,
            'delivery_fee' => $this->delivery_fee,
            'tip' => $this->tip,
            'discount' => $this->discount,
            'total' => $this->total,
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment_method,
            'coupon_code' => $this->coupon_code,
            'notes' => $this->notes,
            'scheduled_at' => $this->scheduled_at,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'waiter' => ($this->relationLoaded('activeWaiter') && $this->activeWaiter?->waiter) ? [
                'id' => $this->activeWaiter->waiter->id,
                'name' => $this->activeWaiter->waiter->name,
            ] : null,
            'items' => $this->whenLoaded('items'),
            'status_history' => $this->whenLoaded('statusHistory'),
            'table' => $this->whenLoaded('table'),
        ];
    }
}
