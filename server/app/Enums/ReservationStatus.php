<?php

namespace App\Enums;

enum ReservationStatus: string
{
    // Canonical V2 Statuses
    case PendingPayment = 'pending_payment';
    case Confirmed      = 'confirmed';
    case Active         = 'active';
    case Expired        = 'expired';
    case Cancelled      = 'cancelled';
    case Refunded       = 'refunded';

    // Legacy / Admin Compatibility
    case Pending        = 'pending';
    case Completed      = 'completed';
    case NoShow         = 'no_show';
    case Redeemed       = 'redeemed';
}
