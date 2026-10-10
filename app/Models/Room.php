<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id', 'room_number', 'type', 'capacity',
        'price_per_night', 'qr_token', 'amenities',
        'image', 'status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities'       => 'array',
            'price_per_night' => 'decimal:2',
            'is_active'       => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function activeBooking(): ?RoomBooking
    {
        return $this->bookings()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->latest()
            ->first();
    }
}
