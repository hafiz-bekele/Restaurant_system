<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'table_id', 'name', 'phone', 'email',
        'party_size', 'reserved_at', 'duration', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'status' => ReservationStatus::class,
            'party_size' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function endsAt(): Carbon
    {
        return $this->reserved_at->addMinutes($this->duration);
    }
}
