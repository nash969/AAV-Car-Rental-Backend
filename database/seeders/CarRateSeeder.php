<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarRate;

class CarRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Toyota Vios (car_id = 2)

        CarRate::create([
            'car_id' => 2,
            'location' => 'within',
            'duration' => '12hrs',
            'price' => 1800
        ]);

        CarRate::create([
            'car_id' => 2,
            'location' => 'within',
            'duration' => '24hrs',
            'price' => 2000
        ]);

        CarRate::create([
            'car_id' => 2,
            'location' => 'outside',
            'duration' => '12hrs',
            'price' => 2000
        ]);

        CarRate::create([
            'car_id' => 2,
            'location' => 'outside',
            'duration' => '24hrs',
            'price' => 2500
        ]);

        // Toyota Veloz (car_id = 3)

        CarRate::create([
            'car_id' => 3,
            'location' => 'within',
            'duration' => '12hrs',
            'price' => 2300
        ]);

        CarRate::create([
            'car_id' => 3,
            'location' => 'within',
            'duration' => '24hrs',
            'price' => 3000
        ]);

        CarRate::create([
            'car_id' => 3,
            'location' => 'outside',
            'duration' => '12hrs',
            'price' => 2500
        ]);

        CarRate::create([
            'car_id' => 3,
            'location' => 'outside',
            'duration' => '24hrs',
            'price' => 3500
        ]);
    }
}