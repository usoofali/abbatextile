<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- Add demo categories ---
        $categories = collect([
            ['name' => 'Shirting', 'description' => 'Textiles for shirts', 'default_unit_type' => 'yard'],
            ['name' => 'Suiting', 'description' => 'Textiles for suits', 'default_unit_type' => 'yard'],
            ['name' => 'Dress', 'description' => 'Dress & eveningwear fabrics', 'default_unit_type' => 'meter'],
            ['name' => 'Outerwear', 'description' => 'Coats and outerwear', 'default_unit_type' => 'meter'],
        ])->map(fn($data) => 
            \App\Models\Category::firstOrCreate(['name' => $data['name']], [
                'id' => (string) Str::uuid(),
                'description' => $data['description'] ?? null,
                'default_unit_type' => $data['default_unit_type'] ?? 'yard',
            ])
        );

        // Create shops with managers and salespersons
        $shops = [
            [
                'name' => 'Downtown Textile Store',
                'location' => '123 Main Street, Downtown',
                'description' => 'Premium textile materials in the heart of downtown',
                'manager' => [
                    'name' => 'John Manager',
                    'email' => 'john@downtown.com',
                ],
                'salespersons' => [
                    ['name' => 'Alice Sales', 'email' => 'alice@downtown.com'],
                    ['name' => 'Bob Sales', 'email' => 'bob@downtown.com'],
                ],
            ],
            [
                'name' => 'Mall Textile Center',
                'location' => '456 Shopping Mall, Westside',
                'description' => 'Modern textile solutions in the shopping mall',
                'manager' => [
                    'name' => 'Jane Manager',
                    'email' => 'jane@mall.com',
                ],
                'salespersons' => [
                    ['name' => 'Charlie Sales', 'email' => 'charlie@mall.com'],
                    ['name' => 'Diana Sales', 'email' => 'diana@mall.com'],
                ],
            ],
        ];

        foreach ($shops as $shopData) {
            // Create manager
            $manager = User::create([
                'id' => (string) Str::uuid(),
                'name' => $shopData['manager']['name'],
                'email' => $shopData['manager']['email'],
                'password' => Hash::make('password'),
                'role' => User::ROLE_MANAGER,
            ]);

            // Create shop
            $shop = Shop::create([
                'id' => (string) Str::uuid(),
                'name' => $shopData['name'],
                'location' => $shopData['location'],
                'description' => $shopData['description'],
                'manager_id' => $manager->id,
            ]);

            // Update manager's shop_id
            $manager->update(['shop_id' => $shop->id]);

            // Create salespersons
            foreach ($shopData['salespersons'] as $salespersonData) {
                User::create([
                    'id' => (string) Str::uuid(),
                    'name' => $salespersonData['name'],
                    'email' => $salespersonData['email'],
                    'password' => Hash::make('password'),
                    'role' => User::ROLE_SALESPERSON,
                    'shop_id' => $shop->id,
                ]);
            }

            // Create sample products for each shop
            $products = [
                [
                    'name' => 'Cotton Fabric - Premium',
                    'description' => 'High-quality cotton fabric perfect for clothing',
                    'price_per_unit' => 12.00,
                    'stock_quantity' => 100.00,
                ],
                [
                    'name' => 'Silk Fabric - Luxury',
                    'description' => 'Premium silk fabric for elegant garments',
                    'price_per_unit' => 35.00,
                    'stock_quantity' => 50.00,
                ],
                [
                    'name' => 'Linen Fabric - Natural',
                    'description' => 'Natural linen fabric for summer clothing',
                    'price_per_unit' => 22.00,
                    'stock_quantity' => 75.00,
                ],
                [
                    'name' => 'Wool Fabric - Winter',
                    'description' => 'Warm wool fabric for winter garments',
                    'price_per_unit' => 28.00,
                    'stock_quantity' => 60.00,
                ],
            ];

            foreach ($products as $productData) {
                Product::create([
                    'id' => (string) Str::uuid(),
                    ...$productData,
                    'shop_id' => $shop->id,
                    'category_id' => $categories->random()->id,
                ]);
            }
        }
    }
}
