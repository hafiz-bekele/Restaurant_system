<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Banner;
use App\Models\DeliveryZone;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Categories
        $categories = [
            [
                'name' => 'Main Courses',
                'slug' => 'main-courses',
                'image' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=500&auto=format&fit=crop&q=80',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Pizza & Pasta',
                'slug' => 'pizza-pasta',
                'image' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=500&auto=format&fit=crop&q=80',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Traditional Specialties',
                'slug' => 'traditional',
                'image' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&auto=format&fit=crop&q=80',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Appetizers & Salads',
                'slug' => 'appetizers',
                'image' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=500&auto=format&fit=crop&q=80',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Desserts',
                'slug' => 'desserts',
                'image' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500&auto=format&fit=crop&q=80',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Signature Beverages',
                'slug' => 'beverages',
                'image' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500&auto=format&fit=crop&q=80',
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];

        $catModels = [];
        foreach ($categories as $cat) {
            $catModels[$cat['slug']] = Category::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        // 2. Dishes
        $dishes = [
            [
                'category_id' => $catModels['main-courses']->id,
                'name' => 'Prime Angus Ribeye Steak',
                'slug' => 'prime-angus-ribeye-steak',
                'description' => 'Char-grilled 300g Prime Angus ribeye with rosemary-garlic butter, truffle mashed potatoes, and roasted asparagus.',
                'price' => 36.50,
                'image' => 'https://images.unsplash.com/photo-1558030006-450675393462?w=700&auto=format&fit=crop&q=80',
                'calories' => 780,
                'prep_time' => 20,
                'is_vegetarian' => false,
                'is_vegan' => false,
                'spice_level' => 0,
                'is_available' => true,
                'is_featured' => true,
                'avg_rating' => 4.95,
                'orders_count' => 142,
                'sort_order' => 1,
            ],
            [
                'category_id' => $catModels['pizza-pasta']->id,
                'name' => 'Artisanal Margherita Pizza',
                'slug' => 'artisanal-margherita-pizza',
                'description' => 'San Marzano tomato sauce, fresh buffalo mozzarella, fragrant basil leaves, and cold-pressed extra virgin olive oil.',
                'price' => 16.00,
                'image' => 'https://images.unsplash.com/photo-1604382355076-af4b0eb60143?w=700&auto=format&fit=crop&q=80',
                'calories' => 650,
                'prep_time' => 15,
                'is_vegetarian' => true,
                'is_vegan' => false,
                'spice_level' => 0,
                'is_available' => true,
                'is_featured' => true,
                'avg_rating' => 4.88,
                'orders_count' => 210,
                'sort_order' => 2,
            ],
            [
                'category_id' => $catModels['traditional']->id,
                'name' => 'Doro Wat Deluxe Feast',
                'slug' => 'doro-wat-deluxe-feast',
                'description' => 'Slow-simmered tender chicken in rich berbere spice sauce with hard-boiled eggs, served with freshly baked teff injera.',
                'price' => 24.00,
                'image' => 'https://images.unsplash.com/photo-1541518763669-27fef04b14ea?w=700&auto=format&fit=crop&q=80',
                'calories' => 620,
                'prep_time' => 18,
                'is_vegetarian' => false,
                'is_vegan' => false,
                'spice_level' => 2,
                'is_available' => true,
                'is_featured' => true,
                'avg_rating' => 4.98,
                'orders_count' => 185,
                'sort_order' => 3,
            ],
            [
                'category_id' => $catModels['pizza-pasta']->id,
                'name' => 'Wild Truffle Fettuccine',
                'slug' => 'wild-truffle-fettuccine',
                'description' => 'Handmade fettuccine ribbons tossed in black truffle cream sauce, shaved Parmigiano-Reggiano, and wild forest mushrooms.',
                'price' => 22.50,
                'image' => 'https://images.unsplash.com/photo-1621996346565-e3d5d6281136?w=700&auto=format&fit=crop&q=80',
                'calories' => 580,
                'prep_time' => 16,
                'is_vegetarian' => true,
                'is_vegan' => false,
                'spice_level' => 0,
                'is_available' => true,
                'is_featured' => true,
                'avg_rating' => 4.92,
                'orders_count' => 98,
                'sort_order' => 4,
            ],
            [
                'category_id' => $catModels['traditional']->id,
                'name' => 'Shiro Tegabino Hot Pot',
                'slug' => 'shiro-tegabino-hot-pot',
                'description' => 'Aromatic powdered chickpea and split-pea clay pot stew cooked with garlic, ginger, and spiced butter. Served sizzling.',
                'price' => 15.00,
                'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=700&auto=format&fit=crop&q=80',
                'calories' => 420,
                'prep_time' => 12,
                'is_vegetarian' => true,
                'is_vegan' => true,
                'spice_level' => 1,
                'is_available' => true,
                'is_featured' => false,
                'avg_rating' => 4.85,
                'orders_count' => 130,
                'sort_order' => 5,
            ],
            [
                'category_id' => $catModels['main-courses']->id,
                'name' => 'Pan-Seared Salmon Fillet',
                'slug' => 'pan-seared-salmon-fillet',
                'description' => 'Crispy skin Atlantic salmon over saffron risotto, baby spinach, and citrus dill beurre blanc.',
                'price' => 28.00,
                'image' => 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?w=700&auto=format&fit=crop&q=80',
                'calories' => 540,
                'prep_time' => 18,
                'is_vegetarian' => false,
                'is_vegan' => false,
                'spice_level' => 0,
                'is_available' => true,
                'is_featured' => true,
                'avg_rating' => 4.87,
                'orders_count' => 84,
                'sort_order' => 6,
            ],
            [
                'category_id' => $catModels['appetizers']->id,
                'name' => 'Crispy Mediterranean Calamari',
                'slug' => 'crispy-mediterranean-calamari',
                'description' => 'Tender flash-fried squid rings with spiced lemon herb seasoning and homemade garlic aioli dip.',
                'price' => 14.50,
                'image' => 'https://images.unsplash.com/photo-1599488615731-7e5c2823ff28?w=700&auto=format&fit=crop&q=80',
                'calories' => 380,
                'prep_time' => 10,
                'is_vegetarian' => false,
                'is_vegan' => false,
                'spice_level' => 1,
                'is_available' => true,
                'is_featured' => false,
                'avg_rating' => 4.76,
                'orders_count' => 112,
                'sort_order' => 7,
            ],
            [
                'category_id' => $catModels['desserts']->id,
                'name' => 'Classic Italian Tiramisu',
                'slug' => 'classic-italian-tiramisu',
                'description' => 'Espresso-soaked ladyfingers layered with velvety mascarpone cream and dusted with Valrhona cocoa.',
                'price' => 9.50,
                'image' => 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=700&auto=format&fit=crop&q=80',
                'calories' => 410,
                'prep_time' => 5,
                'is_vegetarian' => true,
                'is_vegan' => false,
                'spice_level' => 0,
                'is_available' => true,
                'is_featured' => true,
                'avg_rating' => 4.94,
                'orders_count' => 160,
                'sort_order' => 8,
            ],
            [
                'category_id' => $catModels['beverages']->id,
                'name' => 'Passionfruit Mint Spritzer',
                'slug' => 'passionfruit-mint-spritzer',
                'description' => 'Sparkling fresh passionfruit pulp, fresh garden mint, lime juice, and agave nectar over crushed ice.',
                'price' => 6.50,
                'image' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=700&auto=format&fit=crop&q=80',
                'calories' => 120,
                'prep_time' => 5,
                'is_vegetarian' => true,
                'is_vegan' => true,
                'spice_level' => 0,
                'is_available' => true,
                'is_featured' => false,
                'avg_rating' => 4.82,
                'orders_count' => 195,
                'sort_order' => 9,
            ],
        ];

        foreach ($dishes as $d) {
            Dish::firstOrCreate(['slug' => $d['slug']], $d);
        }

        // 3. Banners
        $banners = [
            [
                'title' => 'Chef Signature Dining',
                'subtitle' => 'Experience seasonal creations prepared with Michelin-grade passion.',
                'image' => 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1200&auto=format&fit=crop&q=80',
                'link' => '/menu',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Reserve Your Private Table',
                'subtitle' => 'Intimate candlelight dinners, VIP suites, or vibrant terrace seating.',
                'image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&auto=format&fit=crop&q=80',
                'link' => '/reservations',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Fast Gourmet Delivery',
                'subtitle' => 'Hot and fresh directly to your door in 25 minutes or less.',
                'image' => 'https://images.unsplash.com/photo-1526367790999-0150786686a2?w=1200&auto=format&fit=crop&q=80',
                'link' => '/menu',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($banners as $b) {
            Banner::firstOrCreate(['title' => $b['title']], $b);
        }

        // 4. Delivery Zones
        $zones = [
            ['name' => 'Downtown & Financial District', 'fee' => 2.50, 'min_order' => 15.00, 'radius_km' => 5.0, 'eta_minutes' => 20, 'is_active' => true],
            ['name' => 'Uptown & Residential Hills',    'fee' => 4.50, 'min_order' => 20.00, 'radius_km' => 10.0, 'eta_minutes' => 30, 'is_active' => true],
            ['name' => 'Outer Metro & Suburban Area',    'fee' => 7.00, 'min_order' => 30.00, 'radius_km' => 20.0, 'eta_minutes' => 45, 'is_active' => true],
        ];

        foreach ($zones as $z) {
            DeliveryZone::firstOrCreate(['name' => $z['name']], $z);
        }

        // 5. Coupons
        Coupon::firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'type' => 'percent',
                'value' => 10,
                'min_order' => 20,
                'max_uses' => 500,
                'used_count' => 0,
                'is_active' => true,
                'starts_at' => now(),
                'ends_at' => now()->addMonths(6),
            ]
        );

        // 6. Hotel & Rooms
        $hotel = Hotel::firstOrCreate(
            ['slug' => 'taste-and-stay-boutique-suites'],
            [
                'name' => 'Taste & Stay Bistro & Boutique Suites',
                'slug' => 'taste-and-stay-boutique-suites',
                'address' => 'Bole Medhanialem, Next to Edna Mall, Addis Ababa, Ethiopia',
                'phone' => '+251 91 123 4567',
                'email' => 'hospitality@tasteandstay.et',
                'currency' => 'ETB',
                'is_active' => true,
            ]
        );

        $rooms = [
            ['hotel_id' => $hotel->id, 'room_number' => '101', 'type' => 'Deluxe Queen Suite',   'capacity' => 2, 'price_per_night' => 2800.00, 'status' => 'available', 'is_active' => true, 'qr_token' => Str::random(32), 'amenities' => ['Queen Bed', 'Balcony', 'Room Dining', 'High-Speed Wi-Fi', 'City View']],
            ['hotel_id' => $hotel->id, 'room_number' => '201', 'type' => 'Executive Balcony Suite','capacity' => 4, 'price_per_night' => 4500.00, 'status' => 'available', 'is_active' => true, 'qr_token' => Str::random(32), 'amenities' => ['Living Room', 'Mini Bar', 'Bole Skyline View', 'Jacuzzi', 'Espresso Bar']],
            ['hotel_id' => $hotel->id, 'room_number' => '301', 'type' => 'Presidential Sky Suite',  'capacity' => 6, 'price_per_night' => 8500.00, 'status' => 'available', 'is_active' => true, 'qr_token' => Str::random(32), 'amenities' => ['Private Terrace', 'Personal Chef Service', 'Steam Shower', 'Panoramic Terrace View']],
        ];

        foreach ($rooms as $r) {
            Room::withoutGlobalScopes()->firstOrCreate(['room_number' => $r['room_number']], $r);
        }
    }
}
