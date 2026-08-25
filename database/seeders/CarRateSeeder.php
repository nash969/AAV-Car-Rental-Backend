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
            'price' => 1499
        ]);

        CarRate::create([
            'car_id' => 2,
            'location' => 'within',
            'duration' => '24hrs',
            'price' => 1999
        ]);

        CarRate::create([
            'car_id' => 2,
            'location' => 'outside',
            'duration' => '12hrs',
            'price' => 1999
        ]);

        CarRate::create([
            'car_id' => 2,
            'location' => 'outside',
            'duration' => '24hrs',
            'price' => 2499
        ]);

        CarRate::create([
            'car_id' => 2,
            'location' => 'unli',
            'duration' => '24hrs',
            'price' => 2899
        ]);


        // Toyota Veloz (car_id = 3)

        CarRate::create([
            'car_id' => 3,
            'location' => 'within',
            'duration' => '12hrs',
            'price' => 2399
        ]);

        CarRate::create([
            'car_id' => 3,
            'location' => 'within',
            'duration' => '24hrs',
            'price' => 2799
        ]);

        CarRate::create([
            'car_id' => 3,
            'location' => 'outside',
            'duration' => '12hrs',
            'price' => 2799
        ]);

        CarRate::create([
            'car_id' => 3,
            'location' => 'outside',
            'duration' => '24hrs',
            'price' => 3299
        ]);

        CarRate::create([
            'car_id' => 3,
            'location' => 'unli',
            'duration' => '24hrs',
            'price' => 3799
        ]);
    }
}