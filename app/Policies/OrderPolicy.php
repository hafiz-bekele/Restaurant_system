<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Allow super-admin to bypass all policy checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the order.
     * Managers and staff can view any order; customers can only view their own.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->hasAnyRole(['manager', 'kitchen', 'waiter', 'delivery'])) {
            return true;
        }

        return $order->user_id === $user->id;
    }

    /**
     * Determine whether the user can cancel the order.
     * Only the owning customer (or admin roles handled by before()) may cancel.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
