<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'user_id', 'table_id', 'delivery_zone_id', 'type', 'status',
        'subtotal', 'tax', 'service_charge', 'delivery_fee', 'tip', 'discount', 'total',
        'payment_status', 'payment_method', 'coupon_code',
        'address_snapshot', 'notes', 'scheduled_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'type' => OrderType::class,
            'payment_status' => PaymentStatus::class,
            'address_snapshot' => 'array',
            'subtotal'       => 'decimal:2',
            'tax'            => 'decimal:2',
            'service_charge' => 'decimal:2',
            'delivery_fee'   => 'decimal:2',
            'tip' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (empty($order->ulid)) {
                $order->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function waiterAssignments(): HasMany
    {
        return $this->hasMany(WaiterAssignment::class);
    }

    public function activeWaiter(): HasOne
    {
        return $this->hasOne(WaiterAssignment::class)->where('status', 'assigned')->latestOfMany();
    }

    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [OrderStatus::Pending, OrderStatus::Confirmed]);
    }
}
