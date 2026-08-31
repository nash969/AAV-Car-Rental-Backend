<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleMaintenance extends Model
{
    protected $fillable = [
        'car_id',
        'maintenance_schedule_id',
        'performed_by',
        'mileage',
        'service_type',
        'status',
        'scheduled_date',
        'completed_date',
        'services_performed',
        'findings',
        'notes',
    ];

    protected $casts = [
        'mileage' => 'integer',
        'scheduled_date' => 'date',
        'completed_date' => 'date',
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function schedule()
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}