<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * AdminSeeder
 *
 * Seeds all system staff accounts with strong, unique passwords.
 *
 * IMPORTANT: Change every password below before deploying to production.
 * Do NOT commit real credentials to version control.
 *
 * Default dev passwords (for local testing only):
 * ┌─────────────────────────────────────┬──────────────────────────┐
 * │ Email                               │ Password                 │
 * ├─────────────────────────────────────┼──────────────────────────┤
 * │ superadmin@restaurant.com           │ S@perAdm!n#2026          │
 * │ admin@restaurant.com                │ Adm!nP@ss#2026           │
 * │ manager@restaurant.com              │ M@nager$ecure#2026       │
 * │ reception@restaurant.com            │ R3cept!on#2026           │
 * │ kitchen@restaurant.com              │ K!tchen$t@ff#2026        │
 * │ waiter@restaurant.com               │ W@iterFloor#2026         │
 * │ cashier@restaurant.com              │ C@shierP0S#2026          │
 * │ delivery@restaurant.com             │ D3livery#2026            │
 * │ customer@restaurant.com             │ Cust0mer#2026            │
 * └─────────────────────────────────────┴──────────────────────────┘
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            [
                'email'    => 'superadmin@restaurant.com',
                'name'     => 'Super Admin',
                'password' => env('SEED_SUPERADMIN_PASSWORD', 'S@perAdm!n#2026'),
                'role'     => 'super-admin',
                'hotel_id' => null,
            ],
            [
                'email'    => 'admin@restaurant.com',
                'name'     => 'Hotel Admin',
                'password' => env('SEED_ADMIN_PASSWORD', 'Adm!nP@ss#2026'),
                'role'     => 'admin',
                'hotel_id' => 1,
            ],
            [
                'email'    => 'manager@restaurant.com',
                'name'     => 'Restaurant Manager',
                'password' => env('SEED_MANAGER_PASSWORD', 'M@nager$ecure#2026'),
                'role'     => 'manager',
                'hotel_id' => 1,
            ],
            [
                'email'    => 'reception@restaurant.com',
                'name'     => 'Front Desk Receptionist',
                'password' => env('SEED_RECEPTION_PASSWORD', 'R3cept!on#2026'),
                'role'     => 'reception',
                'hotel_id' => 1,
            ],
            [
                'email'    => 'kitchen@restaurant.com',
                'name'     => 'Head Chef',
                'password' => env('SEED_KITCHEN_PASSWORD', 'K!tchen$t@ff#2026'),
                'role'     => 'kitchen',
                'hotel_id' => 1,
            ],
            [
                'email'    => 'waiter@restaurant.com',
                'name'     => 'Floor Waiter',
                'password' => env('SEED_WAITER_PASSWORD', 'W@iterFloor#2026'),
                'role'     => 'waiter',
                'hotel_id' => 1,
                'on_duty'  => true,
                'section'  => 'Indoor',
            ],
            [
                'email'    => 'cashier@restaurant.com',
                'name'     => 'Cashier',
                'password' => env('SEED_CASHIER_PASSWORD', 'C@shierP0S#2026'),
                'role'     => 'cashier',
                'hotel_id' => 1,
            ],
            [
                'email'    => 'delivery@restaurant.com',
                'name'     => 'Delivery Driver',
                'password' => env('SEED_DELIVERY_PASSWORD', 'D3livery#2026'),
                'role'     => 'delivery',
                'hotel_id' => 1,
            ],
            [
                'email'    => 'customer@restaurant.com',
                'name'     => 'Alex Customer',
                'phone'    => '+251911223344',
                'password' => env('SEED_CUSTOMER_PASSWORD', 'Cust0mer#2026'),
                'role'     => 'customer',
                'hotel_id' => 1,
            ],
        ];

        foreach ($staff as $data) {
            $role = $data['role'];
            unset($data['role']);

            $password = $data['password'];
            unset($data['password']);

            $email = $data['email'];
            unset($data['email']);

            $user = User::firstOrCreate(
                ['email' => $email],
                array_merge($data, [
                    'password' => Hash::make($password),
                    'status'   => 'active',
                ])
            );

            // Re-assign role even if user already existed
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }
}
