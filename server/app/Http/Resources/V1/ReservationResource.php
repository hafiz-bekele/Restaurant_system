<?php

namespace App\Http\Resources\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusStr = $this->status instanceof \App\Enums\ReservationStatus
            ? $this->status->value
            : (string) $this->status;

        $startAt = $this->start_at ?? $this->reserved_at;
        $endAt   = $this->end_at ?? ($startAt ? Carbon::parse($startAt)->addHours($this->duration_hours ?? 1) : null);

        return [
            'id'                  => $this->id,
            'ulid'                => $this->ulid,
            'customer_name'       => $this->customer_name ?? $this->name,
            'name'                => $this->customer_name ?? $this->name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'party_size'          => (int) $this->party_size,
            'start_at'            => $startAt?->toIso8601String(),
            'end_at'              => $endAt?->toIso8601String(),
            'reserved_at'         => $startAt?->toIso8601String(),
            'duration_hours'      => (int) ($this->duration_hours ?? max(1, (int) round(($this->duration ?? 60) / 60))),
            'duration'            => (int) ($this->duration ?? (($this->duration_hours ?? 1) * 60)),
            'status'              => $statusStr,
            'hold_expires_at'     => $this->hold_expires_at?->toIso8601String(),
            'price_base'          => (float) ($this->price_base ?? 0),
            'surcharge_percent'   => (float) ($this->surcharge_percent ?? 0),
            'surcharge_amount'    => (float) ($this->surcharge_amount ?? 0),
            'total_price'         => (float) ($this->total_price ?? $this->reservation_fee ?? 0),
            'currency'            => $this->currency ?? 'ETB',
            'special_requests'    => $this->special_requests ?? $this->notes,
            'notes'               => $this->special_requests ?? $this->notes,
            'paid_at'             => $this->paid_at?->toIso8601String(),
            'remaining_seconds'   => (int) $this->remaining_seconds,
            'seconds_until_start' => (int) $this->seconds_until_start,
            'is_paid'             => $this->isPaid(),
            'is_active_window'    => $this->isActiveWindow(),
            'server_time'         => Carbon::now()->toIso8601String(),
            'table'               => $this->whenLoaded('table', function () {
                return [
                    'id'         => $this->table->id,
                    'name'       => $this->table->name,
                    'area'       => $this->table->area,
                    'capacity'   => $this->table->capacity,
                    'features'   => $this->table->features ?? [],
                    'is_active'  => $this->table->is_active ?? true,
                    'qr_token'   => $this->table->qr_token,
                ];
            }),
            'tx_ref'              => 'RSV-' . $this->ulid,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
