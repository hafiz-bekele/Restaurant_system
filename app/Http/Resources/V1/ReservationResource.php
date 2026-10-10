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
            'status' => $this->status->value,
            'notes' => $this->notes,
            'table' => $this->whenLoaded('table'),
            'created_at' => $this->created_at,
        ];
    }
}
