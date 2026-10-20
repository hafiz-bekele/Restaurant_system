<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'party_size' => $this->party_size,
            'reserved_at' => $this->reserved_at,
            'duration' => $this->duration,
            'status' => $this->status instanceof \App\Enums\ReservationStatus ? $this->status->value : $this->status,
            'notes' => $this->notes,
            'reservation_fee' => (float) ($this->reservation_fee ?? 50.00),
            'initial_fee' => (float) ($this->initial_fee ?? 50.00),
            'fee_status' => $this->fee_status ?? 'pending',
            'redeemed_at' => $this->redeemed_at,
            'redeem_amount' => $this->redeem_amount !== null ? (float) $this->redeem_amount : null,
            'current_fee' => (float) $this->current_fee,
            'elapsed_seconds' => (int) $this->elapsed_seconds,
            'seconds_until_auto_redeem' => (int) $this->seconds_until_auto_redeem,
            'is_redeemed' => $this->status === \App\Enums\ReservationStatus::Redeemed || $this->fee_status === 'redeemed',
            'table' => $this->whenLoaded('table'),
            'created_at' => $this->created_at,
        ];
    }
}
