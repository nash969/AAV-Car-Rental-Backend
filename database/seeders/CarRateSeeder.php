<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Car;
use App\Models\CarRate;

class CarRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vios = Car::firstOrCreate(
            ['brand' => 'Toyota', 'model' => 'Vios'],
            [
                'year' => 2023,
                'price_per_day' => 2000,
                'transmission' => 'Automatic',
                'seats' => 5,
                'fuel_type' => 'Gasoline',
                'available' => true,
            ]
        );

        $veloz = Car::firstOrCreate(
            ['brand' => 'Toyota', 'model' => 'Veloz'],
            [
                'year' => 2023,
                'price_per_day' => 3000,
                'transmission' => 'Automatic',
                'seats' => 7,
                'fuel_type' => 'Gasoline',
                'available' => true,
            ]
        );

        // Rates for Vios
        $viosRates = [
            ['location' => 'within', 'duration' => '12hrs', 'price' => 1800],
            ['location' => 'within', 'duration' => '24hrs', 'price' => 2000],
            ['location' => 'outside', 'duration' => '12hrs', 'price' => 2000],
            ['location' => 'outside', 'duration' => '24hrs', 'price' => 2500],
        ];

        foreach ($viosRates as $rate) {
            CarRate::firstOrCreate([
                'car_id' => $vios->id,
                'location' => $rate['location'],
                'duration' => $rate['duration'],
            ], ['price' => $rate['price']]);
        }

        // Rates for Veloz
        $velozRates = [
            ['location' => 'within', 'duration' => '12hrs', 'price' => 2300],
            ['location' => 'within', 'duration' => '24hrs', 'price' => 3000],
            ['location' => 'outside', 'duration' => '12hrs', 'price' => 2500],
            ['location' => 'outside', 'duration' => '24hrs', 'price' => 3500],
        ];

        foreach ($velozRates as $rate) {
            CarRate::firstOrCreate([
                'car_id' => $veloz->id,
                'location' => $rate['location'],
                'duration' => $rate['duration'],
            ], ['price' => $rate['price']]);
        }
    }
}