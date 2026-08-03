<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
   protected $fillable = [
      'brand',
      'model',
      'year',
      'price_per_day',
      'transmission',
      'seats',
      'fuel_type',
      'image',
      'available',
   ];

   public function rates()
   {
      return $this->hasMany(CarRate::class);
   }
}
