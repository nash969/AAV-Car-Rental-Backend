<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MaintenanceSchedule;

class MaintenanceScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $schedules = [
            [
                'mileage_interval' => 1000,
                'month_interval' => null,
                'service_name' => '1,000 km Initial Inspection',
                'checklist' => 'Initial inspection, engine oil and fluids, tire pressure, brakes, general inspection.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => 5000,
                'month_interval' => null,
                'service_name' => '5,000 km Maintenance',
                'checklist' => 'Check engine oil, tire condition and pressure, brakes, lights, battery, and fluids.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => 10000,
                'month_interval' => null,
                'service_name' => '10,000 km Maintenance',
                'checklist' => 'Engine oil and oil filter replacement, tire rotation, brake inspection, and fluid inspection.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => 20000,
                'month_interval' => null,
                'service_name' => '20,000 km Maintenance',
                'checklist' => 'Oil and filter service, air filter inspection or replacement, brake inspection, suspension and steering check.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => 30000,
                'month_interval' => null,
                'service_name' => '30,000 km Maintenance',
                'checklist' => 'Oil and filter service, detailed inspection, air-conditioning filter, tires, brakes, and suspension.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => 40000,
                'month_interval' => null,
                'service_name' => '40,000 km Major Inspection',
                'checklist' => 'Major inspection; replace or check filters and fluids as required.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => 50000,
                'month_interval' => null,
                'service_name' => '50,000 km Maintenance',
                'checklist' => 'Oil and filter service, tire rotation, brake system, battery, suspension, and cooling system.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => 60000,
                'month_interval' => null,
                'service_name' => '60,000 km Maintenance',
                'checklist' => 'Oil and filter service, tire rotation, brake system, battery, suspension, cooling system, and detailed inspection.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => null,
                'month_interval' => 6,
                'service_name' => '6-Month General Inspection',
                'checklist' => 'General vehicle inspection even if mileage is low.',
                'is_active' => true,
            ],
            [
                'mileage_interval' => null,
                'month_interval' => 12,
                'service_name' => '12-Month General Inspection',
                'checklist' => 'Comprehensive general inspection even if mileage is low.',
                'is_active' => true,
            ],
        ];

        foreach ($schedules as $schedule) {
            MaintenanceSchedule::updateOrCreate(
                [
                    'mileage_interval' => $schedule['mileage_interval'],
                    'month_interval' => $schedule['month_interval'],
                    'service_name' => $schedule['service_name'],
                ],
                $schedule
            );
        }
    }
}