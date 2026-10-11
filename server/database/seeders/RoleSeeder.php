<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $roles = [
            'super-admin',
            'admin',
            'manager',
            'reception',
            'waiter',
            'kitchen',
            'cashier',
            'delivery',
            'customer'
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // 2. Granular Permissions (REQ-2.3 & REQ-2.4)
        $permissionDefs = [
            // User & Access Governance
            'users.view'               => 'View staff and users',
            'users.create'             => 'Create new staff accounts',
            'users.edit'               => 'Edit staff accounts',
            'users.delete'             => 'Delete staff accounts',
            'users.manage_permissions' => 'Assign and grant permissions',

            // Operational Orders & POS
            'orders.view'              => 'View live orders',
            'orders.create'            => 'Create manual and table orders',
            'orders.update_status'     => 'Update order workflow status',
            'orders.reassign_waiter'   => 'Reassign orders to waiters',
            'orders.cancel'            => 'Cancel customer orders',

            // Kitchen Display System
            'kitchen.view_kds'         => 'Access Kitchen Display System',
            'kitchen.update_prep'      => 'Mark dishes as preparing or ready',
            'kitchen.86_stock'         => 'Toggle dish stock availability (86 items)',

            // Cashier & Billing POS
            'cashier.view_pos'         => 'Access Cashier POS terminal',
            'cashier.settle_payment'   => 'Confirm and settle cash/card payments',
            'cashier.reconcile_shift'  => 'Close and reconcile daily cashier shift',
            'cashier.print_receipt'    => 'Print thermal fiscal bills',

            // Front Desk, Tables & Rooms
            'reception.view_floor'     => 'View restaurant table floor plan',
            'reception.manage_tables'  => 'Assign and seat tables',
            'reception.manage_rooms'   => 'Manage hotel suites and guest check-in/out',
            'reservations.manage'      => 'Manage table bookings and reservations',

            // Menu & Catalog
            'menu.view'                => 'View menu catalog',
            'menu.manage'              => 'Create and edit categories, dishes, prices',

            // Inventory & Stock
            'inventory.view'           => 'View inventory stock levels',
            'inventory.manage'         => 'Adjust ingredient stock and purchase orders',

            // Reports & Governance
            'reports.view'             => 'View sales, revenue, and tax reports',
            'audit.view'               => 'Inspect security and activity audit logs',
            'settings.manage'          => 'Manage hotel settings, VAT, service charges',
            'hotels.manage'            => 'Manage multi-tenant hotel properties',
        ];

        foreach ($permissionDefs as $permName => $label) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 3. Assign default permissions to roles
        $superAdminRole = Role::findByName('super-admin', 'web');
        $superAdminRole->syncPermissions(Permission::all());

        $adminRole = Role::findByName('admin', 'web');
        $adminPermissions = Permission::where('name', '!=', 'hotels.manage')->get();
        $adminRole->syncPermissions($adminPermissions);

        $managerRole = Role::findByName('manager', 'web');
        $managerRole->syncPermissions([
            'users.view', 'users.create', 'users.edit',
            'orders.view', 'orders.create', 'orders.update_status', 'orders.reassign_waiter', 'orders.cancel',
            'kitchen.view_kds', 'kitchen.update_prep', 'kitchen.86_stock',
            'cashier.view_pos',
            'reception.view_floor', 'reception.manage_tables', 'reception.manage_rooms', 'reservations.manage',
            'menu.view', 'menu.manage',
            'inventory.view',
            'reports.view',
        ]);

        $cashierRole = Role::findByName('cashier', 'web');
        $cashierRole->syncPermissions([
            'orders.view', 'orders.update_status',
            'cashier.view_pos', 'cashier.settle_payment', 'cashier.reconcile_shift', 'cashier.print_receipt',
        ]);

        $receptionRole = Role::findByName('reception', 'web');
        $receptionRole->syncPermissions([
            'orders.view',
            'reception.view_floor', 'reception.manage_tables', 'reception.manage_rooms', 'reservations.manage',
        ]);

        $waiterRole = Role::findByName('waiter', 'web');
        $waiterRole->syncPermissions([
            'orders.view', 'orders.create',
            'reception.view_floor',
        ]);

        $kitchenRole = Role::findByName('kitchen', 'web');
        $kitchenRole->syncPermissions([
            'orders.view',
            'kitchen.view_kds', 'kitchen.update_prep', 'kitchen.86_stock',
        ]);

        $deliveryRole = Role::findByName('delivery', 'web');
        $deliveryRole->syncPermissions([
            'orders.view', 'orders.update_status',
        ]);

        $customerRole = Role::findByName('customer', 'web');
        $customerRole->syncPermissions([
            'orders.view', 'menu.view',
        ]);
    }
}
