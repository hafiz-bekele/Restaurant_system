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
            ['name' => 'T-01', 'capacity' => 2,  'area' => 'Indoor',  'position_x' => 50,  'position_y' => 50],
            ['name' => 'T-02', 'capacity' => 2,  'area' => 'Indoor',  'position_x' => 150, 'position_y' => 50],
            ['name' => 'T-03', 'capacity' => 4,  'area' => 'Indoor',  'position_x' => 250, 'position_y' => 50],
            ['name' => 'T-04', 'capacity' => 4,  'area' => 'Indoor',  'position_x' => 350, 'position_y' => 50],
            ['name' => 'T-05', 'capacity' => 6,  'area' => 'Indoor',  'position_x' => 50,  'position_y' => 150],
            ['name' => 'T-06', 'capacity' => 6,  'area' => 'Indoor',  'position_x' => 200, 'position_y' => 150],
            ['name' => 'T-07', 'capacity' => 8,  'area' => 'Indoor',  'position_x' => 350, 'position_y' => 150],
            ['name' => 'T-08', 'capacity' => 2,  'area' => 'Outdoor', 'position_x' => 50,  'position_y' => 300],
            ['name' => 'T-09', 'capacity' => 2,  'area' => 'Outdoor', 'position_x' => 150, 'position_y' => 300],
            ['name' => 'T-10', 'capacity' => 4,  'area' => 'Outdoor', 'position_x' => 250, 'position_y' => 300],
            ['name' => 'T-11', 'capacity' => 4,  'area' => 'Outdoor', 'position_x' => 350, 'position_y' => 300],
            ['name' => 'T-12', 'capacity' => 6,  'area' => 'Outdoor', 'position_x' => 450, 'position_y' => 300],
            ['name' => 'VIP-1','capacity' => 10, 'area' => 'VIP',     'position_x' => 100, 'position_y' => 450],
            ['name' => 'VIP-2','capacity' => 10, 'area' => 'VIP',     'position_x' => 300, 'position_y' => 450],
            ['name' => 'BAR-1','capacity' => 4,  'area' => 'Bar',     'position_x' => 500, 'position_y' => 100],
            ['name' => 'BAR-2','capacity' => 4,  'area' => 'Bar',     'position_x' => 500, 'position_y' => 200],
            ['name' => 'BAR-3','capacity' => 2,  'area' => 'Bar',     'position_x' => 500, 'position_y' => 300],
            ['name' => 'PVT-1','capacity' => 12, 'area' => 'Private', 'position_x' => 150, 'position_y' => 550],
            ['name' => 'PVT-2','capacity' => 12, 'area' => 'Private', 'position_x' => 350, 'position_y' => 550],
            ['name' => 'PVT-3','capacity' => 20, 'area' => 'Private', 'position_x' => 250, 'position_y' => 650],
        ];

        foreach ($tables as $data) {
            RestaurantTable::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['status' => 'available', 'qr_token' => Str::random(32)])
            );
        }
    }
}
