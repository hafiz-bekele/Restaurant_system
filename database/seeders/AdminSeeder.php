<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@restaurant.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $superAdmin->assignRole('super-admin');

        // Manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@restaurant.com'],
            [
                'name'     => 'Restaurant Manager',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $manager->assignRole('manager');

        // Receptionist
        $reception = User::firstOrCreate(
            ['email' => 'reception@restaurant.com'],
            [
                'name'     => 'Front Desk',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $reception->assignRole('reception');

        // Kitchen staff
        $kitchen = User::firstOrCreate(
            ['email' => 'kitchen@restaurant.com'],
            [
                'name'     => 'Head Chef',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $kitchen->assignRole('kitchen');

        // Waiter
        $waiter = User::firstOrCreate(
            ['email' => 'waiter@restaurant.com'],
            [
                'name'     => 'Floor Waiter',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $waiter->assignRole('waiter');

        // Cashier
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@restaurant.com'],
            [
                'name'     => 'Cashier',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $cashier->assignRole('cashier');

        // Delivery driver
        $delivery = User::firstOrCreate(
            ['email' => 'delivery@restaurant.com'],
            [
                'name'     => 'Delivery Driver',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );
        $delivery->assignRole('delivery');
    }
}
