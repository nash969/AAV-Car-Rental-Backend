<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demo Accounts
        User::firstOrCreate(
            ['email' => 'admin@aavrental.com'],
            [
                'name' => 'AAV Admin',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'phone' => '09123456789',
            ]
        );

        User::firstOrCreate(
            ['email' => 'employee@aavrental.com'],
            [
                'name' => 'AAV Employee',
                'password' => Hash::make('Employee@123'),
                'role' => 'employee',
                'phone' => '09123456788',
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer@aavrental.com'],
            [
                'name' => 'AAV Customer',
                'password' => Hash::make('Customer@123'),
                'role' => 'customer',
                'phone' => '09123456787',
            ]
        );

        $this->call([
            CarRateSeeder::class,
        ]);
    }
}
