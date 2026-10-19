<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'hotel_id',
        'user_id',
        'opening_float',
        'closing_cash_counted',
        'expected_cash',
        'variance',
        'cash_sales',
        'digital_sales',
        'room_charge_sales',
        'total_sales',
        'orders_count',
        'status',
        'opening_notes',
        'closing_notes',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_float'        => 'decimal:2',
            'closing_cash_counted' => 'decimal:2',
            'expected_cash'        => 'decimal:2',
            'variance'             => 'decimal:2',
            'cash_sales'           => 'decimal:2',
            'digital_sales'        => 'decimal:2',
            'room_charge_sales'    => 'decimal:2',
            'total_sales'          => 'decimal:2',
            'opened_at'            => 'datetime',
            'closed_at'            => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function drawerTransactions(): HasMany
    {
        return $this->hasMany(CashDrawerTransaction::class);
    }

    public function getNetDrawerCashAttribute(): float
    {
        $payIns = (float) $this->drawerTransactions()->where('type', 'pay_in')->sum('amount');
        $payOuts = (float) $this->drawerTransactions()->whereIn('type', ['pay_out', 'drop'])->sum('amount');

        return (float) $this->opening_float + (float) $this->cash_sales + $payIns - $payOuts;
    }
}
