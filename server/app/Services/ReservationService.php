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
    public function getAvailableTables(Carbon $date, int $partySize, int $duration = 90): Collection
    {
        $endTime = $date->clone()->addMinutes($duration);

        $isSqlite = \Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'sqlite';
        $durationSql = $isSqlite
            ? "datetime(reserved_at, '+' || duration || ' minutes') BETWEEN ? AND ?"
            : "DATE_ADD(reserved_at, INTERVAL duration MINUTE) BETWEEN ? AND ?";

        $bookedTableIds = Reservation::where('status', '!=', ReservationStatus::Cancelled->value)
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
        $reservation = Reservation::create([
            'user_id' => $data['user_id'] ?? null,
            'table_id' => $data['table_id'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'party_size' => $data['party_size'],
            'reserved_at' => $data['reserved_at'],
            'duration' => $data['duration'] ?? 90,
            'status' => ReservationStatus::Pending,
            'notes' => $data['notes'] ?? null,
        ]);

        event(new ReservationCreated($reservation));

        return $reservation;
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
