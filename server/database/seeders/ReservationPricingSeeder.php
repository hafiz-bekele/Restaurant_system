<?php

namespace Database\Seeders;

use App\Models\ReservationDaySurcharge;
use App\Models\ReservationPricing;
use Illuminate\Database\Seeder;

class ReservationPricingSeeder extends Seeder
{
    public function run(): void
    {
        // Pricing table (duration to price in ETB)
        $pricing = [
            ['duration_hours' => 1,  'price' => 12.50, 'is_active' => true],
            ['duration_hours' => 2,  'price' => 25.00, 'is_active' => true],
            ['duration_hours' => 3,  'price' => 37.50, 'is_active' => true],
            ['duration_hours' => 6,  'price' => 75.00, 'is_active' => true],
            ['duration_hours' => 12, 'price' => 150.00, 'is_active' => true],
            ['duration_hours' => 18, 'price' => 225.00, 'is_active' => true],
            ['duration_hours' => 24, 'price' => 300.00, 'is_active' => true],
        ];

        foreach ($pricing as $p) {
            ReservationPricing::updateOrCreate(
                ['duration_hours' => $p['duration_hours']],
                $p
            );
        }

        // Day Surcharge table
        $surcharges = [
            ['days_ahead' => 0, 'percent' => 0.00],   // today = +0%
            ['days_ahead' => 1, 'percent' => 25.00],  // tomorrow = +25%
            ['days_ahead' => 2, 'percent' => 40.00],  // 2+ days ahead = +40%
        ];

        foreach ($surcharges as $s) {
            ReservationDaySurcharge::updateOrCreate(
                ['days_ahead' => $s['days_ahead']],
                $s
            );
        }
    }
}
