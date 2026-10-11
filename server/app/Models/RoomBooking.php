<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'room_id', 'guest_name', 'guest_email', 'guest_phone',
        'check_in_date', 'check_out_date', 'status',
        'total_amount', 'paid_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date'  => 'date',
            'check_out_date' => 'date',
            'total_amount'   => 'decimal:2',
            'paid_amount'    => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * REQ-4.3: Check overlap for double-booking prevention.
     * Overlap: (requested_in < existing_out) AND (requested_out > existing_in)
     */
    public static function hasOverlap(int $roomId, string $checkIn, string $checkOut, ?int $excludeId = null): bool
    {
        return static::where('room_id', $roomId)
            ->whereNotIn('status', ['cancelled'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->exists();
    }
}
