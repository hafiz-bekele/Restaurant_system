<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ulid',
        'table_id',
        'customer_name',
        'name',
        'phone',
        'email',
        'party_size',
        'start_at',
        'end_at',
        'reserved_at',
        'duration_hours',
        'duration',
        'status',
        'hold_expires_at',
        'price_base',
        'surcharge_percent',
        'surcharge_amount',
        'total_price',
        'currency',
        'special_requests',
        'notes',
        'paid_at',
        'reservation_fee',
        'initial_fee',
        'fee_status',
        'payment_method',
        'transaction_id',
        'payment_status',
        'is_approved',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'redeemed_at',
        'redeem_amount',
    ];

    protected function casts(): array
    {
        return [
            'start_at'          => 'datetime',
            'end_at'            => 'datetime',
            'reserved_at'       => 'datetime',
            'hold_expires_at'   => 'datetime',
            'paid_at'           => 'datetime',
            'redeemed_at'       => 'datetime',
            'approved_at'       => 'datetime',
            'status'            => ReservationStatus::class,
            'party_size'        => 'integer',
            'duration_hours'    => 'integer',
            'duration'          => 'integer',
            'price_base'        => 'decimal:2',
            'surcharge_percent' => 'decimal:2',
            'surcharge_amount'  => 'decimal:2',
            'total_price'       => 'decimal:2',
            'reservation_fee'   => 'decimal:2',
            'initial_fee'       => 'decimal:2',
            'redeem_amount'     => 'decimal:2',
            'is_approved'       => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $res) {
            if (empty($res->ulid)) {
                $res->ulid = (string) Str::ulid();
            }

            // Sync dual attributes for full backwards & frontend compatibility
            if (empty($res->customer_name) && ! empty($res->name)) {
                $res->customer_name = $res->name;
            } elseif (empty($res->name) && ! empty($res->customer_name)) {
                $res->name = $res->customer_name;
            }

            if (empty($res->special_requests) && ! empty($res->notes)) {
                $res->special_requests = $res->notes;
            } elseif (empty($res->notes) && ! empty($res->special_requests)) {
                $res->notes = $res->special_requests;
            }

            if (empty($res->start_at) && ! empty($res->reserved_at)) {
                $res->start_at = $res->reserved_at;
            } elseif (empty($res->reserved_at) && ! empty($res->start_at)) {
                $res->reserved_at = $res->start_at;
            }

            if (empty($res->duration_hours) && ! empty($res->duration)) {
                $res->duration_hours = max(1, (int) round($res->duration / 60));
            } elseif (empty($res->duration) && ! empty($res->duration_hours)) {
                $res->duration = $res->duration_hours * 60;
            }

            if (empty($res->end_at) && ! empty($res->start_at) && ! empty($res->duration_hours)) {
                $res->end_at = Carbon::parse($res->start_at)->addHours($res->duration_hours);
            }

            if (empty($res->currency)) {
                $res->currency = 'ETB';
            }
        });

        static::updating(function (self $res) {
            // Keep aliases synchronized on updates
            if ($res->isDirty('customer_name')) {
                $res->name = $res->customer_name;
            }
            if ($res->isDirty('special_requests')) {
                $res->notes = $res->special_requests;
            }
            if ($res->isDirty('start_at')) {
                $res->reserved_at = $res->start_at;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'reservation_id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'reservation_id')->latestOfMany();
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null || in_array($this->status, [
            ReservationStatus::Confirmed,
            ReservationStatus::Active,
        ]);
    }

    public function isHoldExpired(): bool
    {
        return $this->status === ReservationStatus::PendingPayment
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isPast();
    }

    /**
     * Return remaining paid duration in seconds from current time until end_at.
     */
    public function getRemainingSecondsAttribute(): int
    {
        if (! $this->end_at) {
            return 0;
        }

        $now = Carbon::now();
        if ($now->greaterThanOrEqualTo($this->end_at)) {
            return 0;
        }

        return $now->diffInSeconds($this->end_at);
    }

    /**
     * Seconds remaining until the reservation starts.
     */
    public function getSecondsUntilStartAttribute(): int
    {
        if (! $this->start_at) {
            return 0;
        }

        $now = Carbon::now();
        if ($now->greaterThanOrEqualTo($this->start_at)) {
            return 0;
        }

        return $now->diffInSeconds($this->start_at);
    }

    /**
     * Live active state check.
     */
    public function isActiveWindow(): bool
    {
        $now = Carbon::now();
        return $this->isPaid()
            && $this->start_at !== null
            && $this->end_at !== null
            && $now->between($this->start_at, $this->end_at);
    }
}
