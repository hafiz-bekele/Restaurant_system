<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    protected $fillable = ['code', 'balance', 'expires_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isValid(): bool
    {
        return $this->is_active
            && $this->balance > 0
            && (! $this->expires_at || now()->lt($this->expires_at));
    }
}
