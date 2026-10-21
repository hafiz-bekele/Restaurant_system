<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            [
                'name'       => 'Table 1 - Window Corner',
                'capacity'   => 2,
                'area'       => 'window',
                'features'   => ['Window View', 'City Panorama', 'Romantic Ambience', 'Candlelit'],
                'position_x' => 50,
                'position_y' => 50,
                'is_active'  => true,
            ],
            [
                'name'       => 'Table 2 - Boulevard Window',
                'capacity'   => 4,
                'area'       => 'window',
                'features'   => ['Window View', 'Sunset Vista', 'Power Outlet', 'Spacious Seating'],
                'position_x' => 150,
                'position_y' => 50,
                'is_active'  => true,
            ],
            [
                'name'       => 'Table 3 - Bistro Center',
                'capacity'   => 4,
                'area'       => 'indoor',
                'features'   => ['AC Climate', 'Plush Booth', 'Close to Bar', 'Warm Lighting'],
                'position_x' => 250,
                'position_y' => 50,
                'is_active'  => true,
            ],
            [
                'name'       => 'Table 4 - Family Dining',
                'capacity'   => 6,
                'area'       => 'indoor',
                'features'   => ['AC Climate', 'High Chair Available', 'Round Table', 'Kids Friendly'],
                'position_x' => 350,
                'position_y' => 50,
                'is_active'  => true,
            ],
            [
                'name'       => 'Table 5 - Garden Veranda',
                'capacity'   => 2,
                'area'       => 'outdoor',
                'features'   => ['Open Sky', 'Garden Breeze', 'Parasol Shade', 'Fresh Air'],
                'position_x' => 50,
                'position_y' => 200,
                'is_active'  => true,
            ],
            [
                'name'       => 'Table 6 - Terrace Grove',
                'capacity'   => 4,
                'area'       => 'outdoor',
                'features'   => ['Open Sky', 'Fountain View', 'Smoking Friendly', 'Heater Lamp'],
                'position_x' => 180,
                'position_y' => 200,
                'is_active'  => true,
            ],
            [
                'name'       => 'Table 7 - Sky Terrace',
                'capacity'   => 8,
                'area'       => 'outdoor',
                'features'   => ['Open Sky', 'Panoramic Skyline', 'Fire Pit Nearby', 'Evening Starlight'],
                'position_x' => 320,
                'position_y' => 200,
                'is_active'  => true,
            ],
            [
                'name'       => 'Table 8 - Library Nook',
                'capacity'   => 2,
                'area'       => 'quiet',
                'features'   => ['Noise Isolated', 'Reading Light', 'Power Outlets', 'High-Speed Wi-Fi'],
                'position_x' => 50,
                'position_y' => 350,
                'is_active'  => true,
            ],
            [
                'name'       => 'Table 9 - Executive Alcove',
                'capacity'   => 6,
                'area'       => 'quiet',
                'features'   => ['Noise Isolated', 'Business Dining', 'Laptop Friendly', 'Acoustic Panels'],
                'position_x' => 200,
                'position_y' => 350,
                'is_active'  => true,
            ],
            [
                'name'       => 'VIP Table 1 - Royal Suite',
                'capacity'   => 10,
                'area'       => 'vip',
                'features'   => ['Dedicated Host', 'Private Booth', 'Champagne Bucket', 'Surround Audio'],
                'position_x' => 100,
                'position_y' => 500,
                'is_active'  => true,
            ],
            [
                'name'       => 'VIP Table 2 - Sovereign Hall',
                'capacity'   => 12,
                'area'       => 'vip',
                'features'   => ['Dedicated Host', 'Private Sommelier', 'Lounge Seating', 'Direct Valet Access'],
                'position_x' => 280,
                'position_y' => 500,
                'is_active'  => true,
            ],
            [
                'name'       => 'Grand Banquet Hall',
                'capacity'   => 20,
                'area'       => 'vip',
                'features'   => ['Exclusive Event Space', 'Private Bar', 'Dedicated Chef Service', 'Projector Ready'],
                'position_x' => 450,
                'position_y' => 500,
                'is_active'  => true,
            ],
        ];

        foreach ($tables as $data) {
            RestaurantTable::updateOrCreate(
                ['name' => $data['name']],
                array_merge($data, [
                    'status'    => 'available',
                    'qr_token'  => Str::random(32),
                    'is_active' => true,
                ])
            );
        }
    }
}
