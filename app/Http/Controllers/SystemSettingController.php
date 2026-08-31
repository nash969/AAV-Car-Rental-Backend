<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function show()
    {
        $settings = SystemSetting::first();

        if (!$settings) {
            $settings = SystemSetting::create([
                'reservation_fee' => 500,
                'payment_methods' => 'GCash, Bank Transfer',
                'rental_policy' =>
                    'Reservation is not refundable if client wishes to cancel. Fee is deductible from total rent amount.',
            ]);
        }

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'reservation_fee' => 'required|numeric|min:0',
            'payment_methods' => 'required|string|max:255',
            'rental_policy' => 'nullable|string',
        ]);

        $settings = SystemSetting::first();

        if (!$settings) {
            $settings = new SystemSetting();
        }

        $settings->fill($validated);
        $settings->save();

        return response()->json([
            'message' => 'Settings updated successfully.',
            'settings' => $settings,
        ]);
    }
}