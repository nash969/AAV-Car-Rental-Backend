<?php

namespace App\Services;

use App\Models\Car;
use App\Models\MaintenanceSchedule;
use App\Models\VehicleMaintenance;
use Carbon\Carbon;

class MaintenanceService
{
    private const DUE_SOON_KM = 500;
    private const DUE_SOON_DAYS = 30;

    public function getVehicleMaintenanceStatus(Car $car): array
    {
        $currentMileage = (int) $car->current_mileage;

        if (!$car->maintenance_initialized) {
            return [
                'status' => 'setup_required',
                'current_mileage' => $currentMileage,

                'next_service_id' => null,
                'next_service' => null,
                'next_service_mileage' => null,
                'remaining_km' => null,

                'next_inspection_date' => null,
                'remaining_days' => null,
                'inspection_date_known' => false,
            ];
        }

        $inspectionBaseDate = $car->last_inspection_date
            ? Carbon::parse($car->last_inspection_date)
            : null;

        $comprehensiveInspectionBaseDate =
            $car->last_comprehensive_inspection_date
                ? Carbon::parse($car->last_comprehensive_inspection_date)
                : null;

        $nextInspectionDate = null;
        $nextComprehensiveInspectionDate = null;
        $remainingDays = null;
        $remainingComprehensiveDays = null;

        if ($inspectionBaseDate) {
            $nextInspectionDate = $inspectionBaseDate
                ->copy()
                ->addMonthsNoOverflow(6);

            $remainingDays = Carbon::today()
                ->diffInDays($nextInspectionDate, false);
        }

        if ($comprehensiveInspectionBaseDate) {
            $nextComprehensiveInspectionDate =
                $comprehensiveInspectionBaseDate
                    ->copy()
                    ->addMonthsNoOverflow(12);

            $remainingComprehensiveDays = Carbon::today()
                ->diffInDays(
                    $nextComprehensiveInspectionDate,
                    false
                );
        }

        $dateStatus = null;

        if ($inspectionBaseDate === null) {
            $dateStatus = 'inspection_required';
        }

        if ($remainingDays !== null) {
            if ($remainingDays < 0) {
                $dateStatus = 'overdue';
            } elseif ($remainingDays === 0) {
                $dateStatus = 'due';
            } elseif ($remainingDays <= self::DUE_SOON_DAYS) {
                $dateStatus = 'due_soon';
            } else {
                $dateStatus = 'good';
            }
        }

        $comprehensiveDateStatus = null;

        if ($comprehensiveInspectionBaseDate === null) {
            $comprehensiveDateStatus = 'inspection_required';
        }

        if ($remainingComprehensiveDays !== null) {
            if ($remainingComprehensiveDays < 0) {
                $comprehensiveDateStatus = 'overdue';
            } elseif ($remainingComprehensiveDays === 0) {
                $comprehensiveDateStatus = 'due';
            } elseif ($remainingComprehensiveDays <= self::DUE_SOON_DAYS) {
                $comprehensiveDateStatus = 'due_soon';
            } else {
                $comprehensiveDateStatus = 'good';
            }
        }

        $dateStatusPriority = [
            'good' => 1,
            'due_soon' => 2,
            'inspection_required' => 3,
            'due' => 4,
            'overdue' => 5,
        ];

        if (
            $comprehensiveDateStatus !== null &&
            (
                $dateStatus === null ||
                $dateStatusPriority[$comprehensiveDateStatus] >
                $dateStatusPriority[$dateStatus]
            )
        ) {
            $dateStatus = $comprehensiveDateStatus;
        }

        $schedules = MaintenanceSchedule::where('is_active', true)
            ->whereNotNull('mileage_interval')
            ->orderBy('mileage_interval')
            ->get();

        foreach ($schedules as $schedule) {
            if (
                $car->maintenance_initialized &&
                $car->maintenance_baseline_mileage !== null &&
                $schedule->mileage_interval < $car->maintenance_baseline_mileage
            ) {
                continue;
            }

            $completed = VehicleMaintenance::where('car_id', $car->id)
                ->where('maintenance_schedule_id', $schedule->id)
                ->where('status', 'completed')
                ->exists();

            if ($completed) {
                continue;
            }

            $remainingKm = $schedule->mileage_interval - $currentMileage;

            if ($remainingKm < 0) {
                $status = 'overdue';
            } elseif ($remainingKm === 0) {
                $status = 'due';
            } elseif ($remainingKm <= self::DUE_SOON_KM) {
                $status = 'due_soon';
            } else {
                $status = 'good';
            }

            $mileageStatus = $status;

            $statusPriority = [
                'good' => 1,
                'due_soon' => 2,
                'inspection_required' => 3,
                'due' => 4,
                'overdue' => 5,
            ];

            if (
                $dateStatus !== null &&
                $statusPriority[$dateStatus] > $statusPriority[$mileageStatus]
            ) {
                $status = $dateStatus;
            }

            return [
                'status' => $status,
                'current_mileage' => $currentMileage,

                'next_service_id' => $schedule->id,
                'next_service' => $schedule->service_name,
                'next_service_mileage' => $schedule->mileage_interval,
                'remaining_km' => max(0, $remainingKm),

                'next_inspection_date' => $nextInspectionDate
                    ? $nextInspectionDate->toDateString()
                    : null,

                'remaining_days' => $remainingDays,

                'next_comprehensive_inspection_date' =>
                    $nextComprehensiveInspectionDate
                        ? $nextComprehensiveInspectionDate->toDateString()
                        : null,

                'remaining_comprehensive_days' =>
                    $remainingComprehensiveDays,

                'inspection_date_known' => $inspectionBaseDate !== null,
            ];
        }

        $highestMileageSchedule = MaintenanceSchedule::where('is_active', true)
            ->whereNotNull('mileage_interval')
            ->max('mileage_interval');

        $scheduleReviewRequired =
            $highestMileageSchedule !== null &&
            $currentMileage > $highestMileageSchedule;

        return [
            'status' => $scheduleReviewRequired
                ? 'schedule_review_required'
                : (
                    $inspectionBaseDate === null
                        ? 'inspection_required'
                        : ($dateStatus ?? 'good')
                ),

            'current_mileage' => $currentMileage,

            'next_service_id' => null,
            'next_service' => null,
            'next_service_mileage' => null,
            'remaining_km' => null,

            'next_inspection_date' => $nextInspectionDate
                ? $nextInspectionDate->toDateString()
                : null,

            'remaining_days' => $remainingDays,

            'next_comprehensive_inspection_date' =>
                $nextComprehensiveInspectionDate
                    ? $nextComprehensiveInspectionDate->toDateString()
                    : null,

            'remaining_comprehensive_days' =>
                $remainingComprehensiveDays,

            'inspection_date_known' => $inspectionBaseDate !== null,
        ];
    }
}