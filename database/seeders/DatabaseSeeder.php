<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // $this->call([
        //     AdminUserSeeder::class,
        //     ShopSeeder::class,
        // ]);
        $this->call([
            AdminUserSeeder::class
        ]);

        // Create default company settings
        \App\Models\CompanySetting::create([
            'company_name' => 'Abba Textiles Nig. Ltd',
            'company_address' => 'Magizawa Plaza, Gusau, Zamfara State',
            'company_phone' => '+234 123 456 7890',
            'company_email' => 'info@abbatextiles.com',
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
        ]);
    }
}
