<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Events\ReservationCreated;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ReservationService
{
    /**
     * Automatically redeem reservations that have reached the 2-hour threshold.
     */
    public function autoRedeemExpiredReservations(): int
    {
        $cutoff = Carbon::now()->subHours(2);

        $expiredReservations = Reservation::whereIn('status', [
            ReservationStatus::Pending->value,
            ReservationStatus::Confirmed->value,
        ])
        ->where('reserved_at', '<=', $cutoff)
        ->get();

        $count = 0;
        foreach ($expiredReservations as $reservation) {
            $reservation->redeem();
            $count++;
        }

        return $count;
    }

    public function getAvailableTables(Carbon $date, int $partySize, int $duration = 60): Collection
    {
        $this->autoRedeemExpiredReservations();

        $endTime = $date->clone()->addMinutes($duration);

        $isSqlite = \Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'sqlite';
        $durationSql = $isSqlite
            ? "datetime(reserved_at, '+' || duration || ' minutes') BETWEEN ? AND ?"
            : "DATE_ADD(reserved_at, INTERVAL duration MINUTE) BETWEEN ? AND ?";

        $bookedTableIds = Reservation::whereNotIn('status', [
                ReservationStatus::Cancelled->value,
                ReservationStatus::Redeemed->value,
                ReservationStatus::NoShow->value,
            ])
            ->where(function ($q) use ($date, $endTime, $durationSql) {
                $q->whereBetween('reserved_at', [$date, $endTime])
                    ->orWhereRaw($durationSql, [$date->toDateTimeString(), $endTime->toDateTimeString()]);
            })
            ->pluck('table_id');

        return RestaurantTable::where('capacity', '>=', $partySize)
            ->where('status', 'available')
            ->whereNotIn('id', $bookedTableIds)
            ->get();
    }

    public function create(array $data): Reservation
    {
        $duration = (int) ($data['duration'] ?? 60);
        $initialFee = 50.00;
        $calculatedFee = Reservation::calculateFeeForMinutes($duration);

        $reservation = Reservation::create([
            'user_id'         => $data['user_id'] ?? null,
            'table_id'        => $data['table_id'],
            'name'            => $data['name'],
            'phone'           => $data['phone'],
            'email'           => $data['email'],
            'party_size'      => $data['party_size'],
            'reserved_at'     => $data['reserved_at'],
            'duration'        => $duration,
            'initial_fee'     => $initialFee,
            'reservation_fee' => $calculatedFee,
            'fee_status'      => $data['fee_status'] ?? 'pending',
            'status'          => ReservationStatus::Pending,
            'notes'           => $data['notes'] ?? null,
        ]);

        event(new ReservationCreated($reservation));

        return $reservation;
    }

    public function redeem(Reservation $reservation, ?float $amount = null): Reservation
    {
        if ($reservation->status === ReservationStatus::Redeemed) {
            return $reservation;
        }

        $reservation->redeem($amount);

        return $reservation->fresh('table');
    }

    public function cancel(Reservation $reservation): Reservation
    {
        if ($reservation->status === ReservationStatus::Cancelled) {
            throw new \RuntimeException('Reservation is already cancelled.');
        }

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        return $reservation;
    }
}
