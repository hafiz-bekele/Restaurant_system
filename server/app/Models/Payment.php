<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'reservation_id',
        'gateway',
        'transaction_id',
        'tx_ref',
        'chapa_reference',
        'amount',
        'currency',
        'status',
        'payload',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'status'      => PaymentStatus::class,
            'amount'      => 'decimal:2',
            'payload'     => 'array',
            'raw_payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
