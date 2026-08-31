<?php

namespace App\Http\Controllers;

use App\Models\VehicleMaintenance;
use Illuminate\Http\Request;
use App\Models\Car;
use App\Services\MaintenanceService;
use App\Models\MaintenanceSchedule;

class VehicleMaintenanceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'employee']),
            403,
            'You are not authorized to view maintenance records.'
        );

        $maintenances = VehicleMaintenance::with([
            'car',
            'schedule',
            'performer'
        ])
            ->latest()
            ->get();

        return response()->json($maintenances);
    }

    public function updateMileage(Request $request, $carId)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'employee']),
            403,
            'You are not authorized to update vehicle mileage.'
        );

        $car = Car::findOrFail($carId);

        if (!$car->maintenance_initialized) {
            return response()->json([
                'message' => 'Initialize maintenance tracking before updating vehicle mileage.',
            ], 422);
        }

        $hasOngoingMaintenance = VehicleMaintenance::where('car_id', $car->id)
            ->where('status', 'ongoing')
            ->exists();

        if ($hasOngoingMaintenance) {
            return response()->json([
                'message' => 'Vehicle mileage cannot be updated while maintenance is ongoing.',
            ], 422);
        }

        $validated = $request->validate([
            'current_mileage' => [
                'required',
                'integer',
                'min:' . $car->current_mileage,
            ],
        ]);

        $car->update([
            'current_mileage' => $validated['current_mileage'],
        ]);

        return response()->json([
            'message' => 'Vehicle mileage updated successfully.',
            'car' => $car,
        ]);
    }

    public function initializeVehicle(Request $request, $carId)
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Only administrators can initialize vehicle maintenance.'
        );

        $car = Car::findOrFail($carId);

        if ($car->maintenance_initialized) {
            return response()->json([
                'message' => 'Maintenance tracking has already been initialized for this vehicle.',
            ], 422);
        }

        $validated = $request->validate([
            'current_mileage' => [
                'required',
                'integer',
                'min:0',
            ],
            'last_inspection_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],
        ]);

        $car->update([
            'current_mileage' => $validated['current_mileage'],
            'maintenance_baseline_mileage' => $validated['current_mileage'],
            'maintenance_baseline_date' => now()->toDateString(),
            'last_inspection_date' => $validated['last_inspection_date'] ?? null,
            'last_comprehensive_inspection_date' => $validated['last_inspection_date'] ?? null,
            'maintenance_initialized' => true,
        ]);

        return response()->json([
            'message' => 'Vehicle maintenance tracking initialized successfully.',
            'car' => $car->fresh(),
        ]);
    }

    public function vehicles(Request $request, MaintenanceService $maintenanceService)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'employee']),
            403,
            'You are not authorized to view vehicle maintenance status.'
        );

        $vehicles = Car::with([
                'maintenances' => function ($query) {
                    $query->where('status', 'ongoing');
                }
            ])
            ->orderBy('brand')
            ->orderBy('model')
            ->get()
            ->map(function ($car) use ($maintenanceService) {
                return [
                    'id' => $car->id,
                    'brand' => $car->brand,
                    'model' => $car->model,
                    'current_mileage' => $car->current_mileage,
                    'available' => $car->available,
                    'maintenance_initialized' => $car->maintenance_initialized,
                    'maintenance_baseline_mileage' => $car->maintenance_baseline_mileage,
                    'maintenance_baseline_date' => $car->maintenance_baseline_date,
                    'last_inspection_date' => $car->last_inspection_date,
                    'has_ongoing_maintenance' => $car->maintenances->isNotEmpty(),
                    'ongoing_maintenance_id' => $car->maintenances->first()?->id,
                    'ongoing_maintenance_service' => $car->maintenances->first()?->service_type,
                    'maintenance' => $maintenanceService
                        ->getVehicleMaintenanceStatus($car),
                ];
            });

        return response()->json($vehicles);
    }

    public function schedules(Request $request)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'employee']),
            403,
            'You are not authorized to view maintenance schedules.'
        );

        $schedules = MaintenanceSchedule::where('is_active', true)
            ->orderByRaw('mileage_interval IS NULL, mileage_interval')
            ->orderBy('month_interval')
            ->get();

        return response()->json($schedules);
    }

    public function start(Request $request, $carId)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'employee']),
            403,
            'You are not authorized to start vehicle maintenance.'
        );

        $car = Car::findOrFail($carId);

        if (!$car->maintenance_initialized) {
            return response()->json([
                'message' => 'Initialize maintenance tracking before starting maintenance.',
            ], 422);
        }

        $now = now();

        $hasBookingConflict = $car->bookings()
            ->whereIn('status', ['confirmed', 'ongoing'])
            ->where('pickup_date', '<=', $now)
            ->where('return_date', '>=', $now)
            ->exists();

        if ($hasBookingConflict) {
            return response()->json([
                'message' => 'Maintenance cannot be started while the vehicle has an active confirmed or ongoing rental.',
            ], 422);
        }

        $ongoingMaintenance = VehicleMaintenance::where('car_id', $car->id)
            ->where('status', 'ongoing')
            ->exists();

        if ($ongoingMaintenance) {
            return response()->json([
                'message' => 'This vehicle already has an ongoing maintenance record.',
            ], 422);
        }

        $validated = $request->validate([
            'maintenance_schedule_id' => [
                'nullable',
                'exists:maintenance_schedules,id',
            ],
            'service_type' => [
                'required',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $maintenance = VehicleMaintenance::create([
            'car_id' => $car->id,
            'maintenance_schedule_id' =>
                $validated['maintenance_schedule_id'] ?? null,
            'performed_by' => $request->user()->id,
            'mileage' => $car->current_mileage,
            'service_type' => $validated['service_type'],
            'status' => 'ongoing',
            'scheduled_date' => now()->toDateString(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $car->update([
            'available' => false,
        ]);

        return response()->json([
            'message' => 'Vehicle maintenance started successfully.',
            'maintenance' => $maintenance->load([
                'car',
                'schedule',
                'performer',
            ]),
        ], 201);
    }

    public function complete(Request $request, $maintenanceId)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'employee']),
            403,
            'You are not authorized to complete vehicle maintenance.'
        );

        $maintenance = VehicleMaintenance::with('car')
            ->findOrFail($maintenanceId);

        if ($maintenance->status !== 'ongoing') {
            return response()->json([
                'message' => 'Only ongoing maintenance can be completed.',
            ], 422);
        }

        $validated = $request->validate([
            'services_performed' => [
                'required',
                'string',
            ],
            'findings' => [
                'nullable',
                'string',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $maintenance->update([
            'status' => 'completed',
            'completed_date' => now()->toDateString(),
            'services_performed' => $validated['services_performed'],
            'findings' => $validated['findings'] ?? null,
            'notes' => $validated['notes'] ?? $maintenance->notes,
            'performed_by' => $request->user()->id,
        ]);

        if ($maintenance->maintenance_schedule_id === 9) {
            $maintenance->car->update([
                'last_inspection_date' => now()->toDateString(),
            ]);
        }

        if ($maintenance->maintenance_schedule_id === 10) {
            $maintenance->car->update([
                'last_comprehensive_inspection_date' => now()->toDateString(),
            ]);
        }

        $hasActiveBooking = $maintenance->car->bookings()
            ->whereIn('status', ['confirmed', 'ongoing'])
            ->exists();

        $maintenance->car->update([
            'available' => !$hasActiveBooking,
        ]);

        return response()->json([
            'message' => 'Vehicle maintenance completed successfully.',
            'maintenance' => $maintenance->fresh()->load([
                'car',
                'schedule',
                'performer',
            ]),
        ]);
    }
}