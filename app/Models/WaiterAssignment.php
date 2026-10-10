<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaiterAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'waiter_id', 'status',
        'assigned_at', 'accepted_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at'  => 'datetime',
            'accepted_at'  => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }
}
