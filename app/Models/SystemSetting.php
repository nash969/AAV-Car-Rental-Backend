<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'reservation_fee',
        'payment_methods',
        'rental_policy',
    ];
}