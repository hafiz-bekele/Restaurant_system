<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
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
     * Determine whether the user can view the reservation.
     * Managers may view any; guests (no user_id) and owners may view their own.
     */
    public function view(?User $user, Reservation $reservation): bool
    {
        if ($user && $user->hasRole('manager')) {
            return true;
        }

        if ($reservation->user_id === null) {
            return true;
        }

        return $user?->id === $reservation->user_id;
    }

    /**
     * Determine whether the user can update the reservation.
     */
    public function update(?User $user, Reservation $reservation): bool
    {
        if ($user && $user->hasRole('manager')) {
            return true;
        }

        return $user?->id === $reservation->user_id;
    }

    /**
     * Determine whether the user can cancel (delete) the reservation.
     */
    public function delete(?User $user, Reservation $reservation): bool
    {
        if ($user && $user->hasRole('manager')) {
            return true;
        }

        return $user?->id === $reservation->user_id;
    }
}
