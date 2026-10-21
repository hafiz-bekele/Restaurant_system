<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RestaurantTable extends Model
{
    use HasFactory;

    protected $table = 'tables';

    protected $fillable = [
        'name', 'capacity', 'qr_token', 'area',
        'position_x', 'position_y', 'status', 'features', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status'     => TableStatus::class,
            'capacity'   => 'integer',
            'position_x' => 'decimal:2',
            'position_y' => 'decimal:2',
            'features'   => 'array',
            'is_active'  => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $table) {
            if (empty($table->qr_token)) {
                $table->qr_token = Str::random(32);
            }
        });
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === TableStatus::Available;
    }
}
