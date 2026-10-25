<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'loyalty_points' => $this->loyalty_points ?? 0,
            'status' => $this->status instanceof \App\Enums\UserStatus ? $this->status->value : ($this->status ?? 'active'),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'hotel_id' => $this->hotel_id,
            'section' => $this->section,
            'on_duty' => (bool) $this->on_duty,
            'email_verified' => $this->hasVerifiedEmail(),
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'created_at' => $this->created_at,
        ];
    }
}
