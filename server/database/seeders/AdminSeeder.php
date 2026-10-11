<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin (System-wide, All Tenants)
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@restaurant.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => null,
            ]
        );
        $superAdmin->assignRole('super-admin');

        // Hotel Admin (Property-level administration)
        $admin = User::firstOrCreate(
            ['email' => 'admin@restaurant.com'],
            [
                'name'     => 'Hotel Admin',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => 1,
            ]
        );
        $admin->assignRole('admin');

        // Hotel Manager (Operations & Shift Management)
        $manager = User::firstOrCreate(
            ['email' => 'manager@restaurant.com'],
            [
                'name'     => 'Restaurant Manager',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => 1,
            ]
        );
        $manager->assignRole('manager');

        // Front Desk / Reception
        $reception = User::firstOrCreate(
            ['email' => 'reception@restaurant.com'],
            [
                'name'     => 'Front Desk Receptionist',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => 1,
            ]
        );
        $reception->assignRole('reception');

        // Head Chef / Kitchen Staff
        $kitchen = User::firstOrCreate(
            ['email' => 'kitchen@restaurant.com'],
            [
                'name'     => 'Head Chef',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => 1,
            ]
        );
        $kitchen->assignRole('kitchen');

        // Floor Waiter / Server
        $waiter = User::firstOrCreate(
            ['email' => 'waiter@restaurant.com'],
            [
                'name'     => 'Floor Waiter',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => 1,
                'on_duty'  => true,
                'section'  => 'Indoor',
            ]
        );
        $waiter->assignRole('waiter');

        // Cashier / Billing POS
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@restaurant.com'],
            [
                'name'     => 'Cashier',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => 1,
            ]
        );
        $cashier->assignRole('cashier');

        // Delivery Driver
        $delivery = User::firstOrCreate(
            ['email' => 'delivery@restaurant.com'],
            [
                'name'     => 'Delivery Driver',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => 1,
            ]
        );
        $delivery->assignRole('delivery');

        // Customer (Online Diner / Guest)
        $customer = User::firstOrCreate(
            ['email' => 'customer@restaurant.com'],
            [
                'name'     => 'Alex Customer',
                'phone'    => '+251911223344',
                'password' => Hash::make('password'),
                'status'   => 'active',
                'hotel_id' => 1,
            ]
        );
        $customer->assignRole('customer');
    }
}
