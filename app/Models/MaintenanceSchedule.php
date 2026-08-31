<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    protected $fillable = [
        'mileage_interval',
        'month_interval',
        'service_name',
        'checklist',
        'is_active',
    ];

    protected $casts = [
        'mileage_interval' => 'integer',
        'month_interval' => 'integer',
        'is_active' => 'boolean',
    ];
}