<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Car;
use App\Models\CarRate;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProductionDataSeeder extends Seeder
{
    public function run(): void
    {
        $vios = Car::updateOrCreate(
            [
                'brand' => 'Toyota',
                'model' => 'vios',
            ],
            [
                'vehicle_type' => 'sedan',
                'traccar_device_id' => 1,
                'year' => 2024,
                'price_per_day' => 1999,
                'transmission' => 'automatic',
                'seats' => 5,
                'fuel_type' => 'gasoline',
                'image' => 'assets/toyota-vios.jpg',
                'available' => true,
                'current_mileage' => 0,
                'maintenance_initialized' => false,
            ]
        );

        $veloz = Car::updateOrCreate(
            [
                'brand' => 'Toyota',
                'model' => 'Veloz',
            ],
            [
                'vehicle_type' => 'SUV',
                'traccar_device_id' => null,
                'year' => 2024,
                'price_per_day' => 2799,
                'transmission' => 'automatic',
                'seats' => 7,
                'fuel_type' => 'gasoline',
                'image' => '/storage/vehicle-images/AaZXIUdkOnCK47GQhCsjlztG3B8JK2VMZpnBEYF8.jpg',
                'available' => true,
                'current_mileage' => 0,
                'maintenance_initialized' => false,
            ]
        );

        $rates = [
            [$vios->id, 'within', '12hrs', 1499],
            [$vios->id, 'within', '24hrs', 1999],
            [$vios->id, 'outside', '12hrs', 1999],
            [$vios->id, 'outside', '24hrs', 2499],
            [$vios->id, 'unli', '24hrs', 2899],

            [$veloz->id, 'within', '12hrs', 2399],
            [$veloz->id, 'within', '24hrs', 2799],
            [$veloz->id, 'outside', '12hrs', 2799],
            [$veloz->id, 'outside', '24hrs', 3299],
            [$veloz->id, 'unli', '24hrs', 3799],
        ];

        foreach ($rates as [$carId, $location, $duration, $price]) {
            CarRate::updateOrCreate(
                [
                    'car_id' => $carId,
                    'location' => $location,
                    'duration' => $duration,
                ],
                [
                    'price' => $price,
                ]
            );
        }

        SystemSetting::updateOrCreate(
            ['id' => 1],
            [
                'reservation_fee' => 500,
                'payment_methods' => 'GCash, Bank Transfer',
                'rental_policy' => 'Reservation is not refundable if client wishes to cancel. Fee is deductible from total rent amount.',
            ]
        );

        $this->call(MaintenanceScheduleSeeder::class);

        $adminPassword = env('PRODUCTION_ADMIN_PASSWORD');

        if ($adminPassword) {
            User::updateOrCreate(
                ['email' => 'admin@aav.com'],
                [
                    'name' => 'System Admin',
                    'phone' => '09123456789',
                    'role' => 'admin',
                    'password' => Hash::make($adminPassword),
                ]
            );
        }
    }
}