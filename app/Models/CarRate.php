<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarRate extends Model
{
    protected $fillable = [
        'car_id',
        'location',
        'duration',
        'price'
    ];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}