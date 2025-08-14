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
            'username' => 'admin_user',
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('password'),
            'role_code' => 'admin',
            'full_name' => 'Admin User',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'employee1',
            'email' => 'emp1@example.com',
            'password_hash' => Hash::make('password'),
            'role_code' => 'employee',
            'full_name' => 'Alice Smith',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'employee2',
            'email' => 'emp2@example.com',
            'password_hash' => Hash::make('password'),
            'role_code' => 'employee',
            'full_name' => 'Bob Johnson',
            'is_active' => true,
        ]);
    }
}
