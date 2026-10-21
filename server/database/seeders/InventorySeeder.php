<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Suppliers
        $suppliersData = [
            [
                'name'         => 'Highland Fresh Organic Farms',
                'contact_name' => 'Selamawit Tadesse',
                'phone'        => '+251 91 123 7788',
                'email'        => 'selam@highlandfarms.et',
                'address'      => 'Debre Zeit Road, Oromia Region',
                'is_active'    => true,
            ],
            [
                'name'         => 'Abyssinia Premium Meats & Poultry',
                'contact_name' => 'Dawit Bekele',
                'phone'        => '+251 91 234 5566',
                'email'        => 'sales@abyssiniameats.et',
                'address'      => 'Kality Industrial Zone, Addis Ababa',
                'is_active'    => true,
            ],
            [
                'name'         => 'Red Sea Fishery & Cold Chain',
                'contact_name' => 'Michael Gebre',
                'phone'        => '+251 91 345 6677',
                'email'        => 'michael@redseafish.et',
                'address'      => 'Bole Sub-City, Addis Ababa',
                'is_active'    => true,
            ],
            [
                'name'         => 'Italian Artisan Goods & Dairy',
                'contact_name' => 'Marco Rossi',
                'phone'        => '+251 91 456 7788',
                'email'        => 'marco@italiangoods.et',
                'address'      => 'Kazanchis, Addis Ababa',
                'is_active'    => true,
            ],
            [
                'name'         => 'Ethio Organic Specialty Coffee',
                'contact_name' => 'Tigist Alemu',
                'phone'        => '+251 91 567 8899',
                'email'        => 'tigist@ethiocoffee.et',
                'address'      => 'Sidama & Yirgacheffe Supply, Addis Ababa',
                'is_active'    => true,
            ],
        ];

        $suppliers = [];
        foreach ($suppliersData as $s) {
            $suppliers[$s['name']] = Supplier::firstOrCreate(['name' => $s['name']], $s);
        }

        // 2. Ingredients
        $ingredientsData = [
            [
                'name'          => 'San Marzano Tomatoes',
                'unit'          => 'kg',
                'stock'         => 45.0,
                'reorder_level' => 15.0,
                'cost'          => 85.00,
                'supplier_name' => 'Italian Artisan Goods & Dairy',
            ],
            [
                'name'          => 'Fresh Buffalo Mozzarella',
                'unit'          => 'kg',
                'stock'         => 12.5,
                'reorder_level' => 8.0,
                'cost'          => 350.00,
                'supplier_name' => 'Italian Artisan Goods & Dairy',
            ],
            [
                'name'          => 'Prime Angus Beef Ribeye',
                'unit'          => 'kg',
                'stock'         => 28.0,
                'reorder_level' => 10.0,
                'cost'          => 650.00,
                'supplier_name' => 'Abyssinia Premium Meats & Poultry',
            ],
            [
                'name'          => 'Free-Range Chicken (Doro)',
                'unit'          => 'kg',
                'stock'         => 35.0,
                'reorder_level' => 12.0,
                'cost'          => 240.00,
                'supplier_name' => 'Abyssinia Premium Meats & Poultry',
            ],
            [
                'name'          => 'Fresh Atlantic Salmon Fillet',
                'unit'          => 'kg',
                'stock'         => 5.5, // Low stock!
                'reorder_level' => 8.0,
                'cost'          => 720.00,
                'supplier_name' => 'Red Sea Fishery & Cold Chain',
            ],
            [
                'name'          => 'Mediterranean Calamari Rings',
                'unit'          => 'kg',
                'stock'         => 14.0,
                'reorder_level' => 6.0,
                'cost'          => 420.00,
                'supplier_name' => 'Red Sea Fishery & Cold Chain',
            ],
            [
                'name'          => 'Pure Teff Flour (Injera)',
                'unit'          => 'kg',
                'stock'         => 85.0,
                'reorder_level' => 25.0,
                'cost'          => 65.00,
                'supplier_name' => 'Highland Fresh Organic Farms',
            ],
            [
                'name'          => 'Artisanal Berbere Spice Blend',
                'unit'          => 'kg',
                'stock'         => 3.5, // Low stock!
                'reorder_level' => 5.0,
                'cost'          => 380.00,
                'supplier_name' => 'Highland Fresh Organic Farms',
            ],
            [
                'name'          => 'Nitir Qibe (Spiced Ethiopian Butter)',
                'unit'          => 'kg',
                'stock'         => 18.0,
                'reorder_level' => 6.0,
                'cost'          => 450.00,
                'supplier_name' => 'Highland Fresh Organic Farms',
            ],
            [
                'name'          => 'Shiro Powder (Special Blend)',
                'unit'          => 'kg',
                'stock'         => 30.0,
                'reorder_level' => 10.0,
                'cost'          => 120.00,
                'supplier_name' => 'Highland Fresh Organic Farms',
            ],
            [
                'name'          => 'Yirgacheffe Specialty Coffee Beans',
                'unit'          => 'kg',
                'stock'         => 22.0,
                'reorder_level' => 8.0,
                'cost'          => 480.00,
                'supplier_name' => 'Ethio Organic Specialty Coffee',
            ],
            [
                'name'          => 'Italian Mascarpone Cheese',
                'unit'          => 'kg',
                'stock'         => 4.0, // Low stock!
                'reorder_level' => 6.0,
                'cost'          => 520.00,
                'supplier_name' => 'Italian Artisan Goods & Dairy',
            ],
            [
                'name'          => 'Fresh Garden Mint Leaves',
                'unit'          => 'bunch',
                'stock'         => 40.0,
                'reorder_level' => 15.0,
                'cost'          => 25.00,
                'supplier_name' => 'Highland Fresh Organic Farms',
            ],
            [
                'name'          => 'Fresh Passionfruit Pulp',
                'unit'          => 'kg',
                'stock'         => 15.0,
                'reorder_level' => 5.0,
                'cost'          => 160.00,
                'supplier_name' => 'Highland Fresh Organic Farms',
            ],
            [
                'name'          => 'Black Truffle Oil (Italian Extra Virgin)',
                'unit'          => 'bottle',
                'stock'         => 2.0, // Low stock!
                'reorder_level' => 4.0,
                'cost'          => 950.00,
                'supplier_name' => 'Italian Artisan Goods & Dairy',
            ],
        ];

        foreach ($ingredientsData as $ing) {
            $sup = $suppliers[$ing['supplier_name']] ?? null;
            $ingredient = Ingredient::firstOrCreate(
                ['name' => $ing['name']],
                [
                    'unit'          => $ing['unit'],
                    'stock'         => $ing['stock'],
                    'reorder_level' => $ing['reorder_level'],
                    'cost'          => $ing['cost'],
                    'supplier_id'   => $sup?->id,
                ]
            );

            // Record initial movement
            StockMovement::firstOrCreate(
                [
                    'ingredient_id' => $ingredient->id,
                    'reason'        => 'Initial stock intake',
                ],
                [
                    'delta'      => $ing['stock'],
                    'reason'     => 'Initial stock intake',
                    'created_at' => now()->subDays(rand(1, 10)),
                ]
            );
        }
    }
}
