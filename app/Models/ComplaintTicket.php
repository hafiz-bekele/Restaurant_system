<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ComplaintTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id', 'order_id', 'user_id', 'ticket_number',
        'category', 'subject', 'description',
        'status', 'resolution_note', 'assigned_to', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (self $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TCK-' . strtoupper(Str::random(6));
            }
        });
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
