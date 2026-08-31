<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
   protected $fillable = [
      'brand',
      'model',
      'vehicle_type',
      'traccar_device_id',
      'year',
      'price_per_day',
      'transmission',
      'seats',
      'fuel_type',
      'image',
      'available',
      'current_mileage',
      'maintenance_baseline_mileage',
      'maintenance_baseline_date',
      'last_inspection_date',
      'last_comprehensive_inspection_date',
      'maintenance_initialized',
   ];

   protected $casts = [
      'current_mileage' => 'integer',
      'maintenance_baseline_mileage' => 'integer',
      'maintenance_baseline_date' => 'date',
      'last_inspection_date' => 'date',
      'last_comprehensive_inspection_date' => 'date',
      'maintenance_initialized' => 'boolean',
   ];

   public function rates()
   {
      return $this->hasMany(CarRate::class);
   }

   public function maintenances()
   {
      return $this->hasMany(VehicleMaintenance::class);
   }

   public function bookings()
   {
      return $this->hasMany(Booking::class);
   }
}