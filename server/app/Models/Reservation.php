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
        'reservation_fee', 'initial_fee', 'fee_status', 'redeemed_at', 'redeem_amount',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'status' => ReservationStatus::class,
            'party_size' => 'integer',
            'duration' => 'integer',
            'reservation_fee' => 'decimal:2',
            'initial_fee' => 'decimal:2',
            'redeemed_at' => 'datetime',
            'redeem_amount' => 'decimal:2',
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

    /**
     * Compute fee for a duration in seconds:
     * Up to 1 hour (3600s): 50.00 ETB
     * Overtime after 1 hour: +5.00 ETB every 10 seconds
     * Max 2 hours (7200s) at which reservation redeems.
     */
    public static function calculateFeeForSeconds(int $seconds): float
    {
        $baseFee = 50.00;
        $baseSeconds = 3600; // 1 hour

        // Cap at 2 hours (7200 seconds)
        $effectiveSeconds = min(max(0, $seconds), 7200);

        if ($effectiveSeconds <= $baseSeconds) {
            return $baseFee;
        }

        $extraSeconds = $effectiveSeconds - $baseSeconds;
        $intervals = (int) floor($extraSeconds / 10);

        return round($baseFee + ($intervals * 5.00), 2);
    }

    public static function calculateFeeForMinutes(int $minutes): float
    {
        return self::calculateFeeForSeconds($minutes * 60);
    }

    /**
     * Live current fee based on elapsed time or booked duration.
     */
    public function getCurrentFeeAttribute(): float
    {
        if ($this->fee_status === 'redeemed' && $this->redeem_amount !== null) {
            return (float) $this->redeem_amount;
        }

        if (! $this->reserved_at) {
            return (float) ($this->reservation_fee ?? 50.00);
        }

        $now = Carbon::now();
        if ($now->lessThanOrEqualTo($this->reserved_at)) {
            return (float) ($this->reservation_fee ?? self::calculateFeeForMinutes($this->duration ?? 60));
        }

        $elapsedSeconds = $this->reserved_at->diffInSeconds($now);
        return self::calculateFeeForSeconds($elapsedSeconds);
    }

    public function getElapsedSecondsAttribute(): int
    {
        if (! $this->reserved_at) {
            return 0;
        }
        $now = Carbon::now();
        if ($now->lessThan($this->reserved_at)) {
            return 0;
        }
        return min(7200, $this->reserved_at->diffInSeconds($now));
    }

    public function getSecondsUntilAutoRedeemAttribute(): int
    {
        if (! $this->reserved_at || $this->status === ReservationStatus::Redeemed) {
            return 0;
        }
        $autoRedeemTime = $this->reserved_at->copy()->addHours(2);
        $now = Carbon::now();
        if ($now->greaterThanOrEqualTo($autoRedeemTime)) {
            return 0;
        }
        return $now->diffInSeconds($autoRedeemTime);
    }

    public function isRedeemable(): bool
    {
        return in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed]);
    }

    public function redeem(?float $amount = null): self
    {
        $fee = $amount ?? $this->current_fee;
        $this->update([
            'status' => ReservationStatus::Redeemed,
            'fee_status' => 'redeemed',
            'redeemed_at' => Carbon::now(),
            'redeem_amount' => $fee,
        ]);
        return $this;
    }
}
