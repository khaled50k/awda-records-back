<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the StaticDataSeeder first
        $this->call([
            StaticDataSeeder::class,
        ]);

        // Create sample users
        User::create([
            'username' => 'awda-admin',
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('awdaAdmin2025@**'),
            'role_code' => 'admin',
            'full_name' => 'Admin User',
            'is_active' => true,
        ]);

    
    }
}
