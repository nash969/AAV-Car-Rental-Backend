<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Car;

class TraccarController extends Controller
{

    public function location(int $id)
    {
        $car = Car::find($id);

        if (!$car) {
            return response()->json([
                'message' => 'Vehicle not found.'
            ], 404);
        }

        if (!$car->traccar_device_id) {
            return response()->json([
                'message' => 'This vehicle does not have a GPS tracker assigned.'
            ], 404);
        }

        $deviceId = $car->traccar_device_id;

        $traccarUrl = config('services.traccar.url');

        $auth = [
            config('services.traccar.email'),
            config('services.traccar.password')
        ];

        $positionResponse = Http::withBasicAuth(...$auth)
            ->get(
                "{$traccarUrl}/api/positions",
                ['deviceId' => $deviceId]
            );

        $deviceResponse = Http::withBasicAuth(...$auth)
            ->get(
                "{$traccarUrl}/api/devices",
                ['id' => $deviceId]
            );

        if (
            $positionResponse->failed() ||
            $deviceResponse->failed()
        ) {
            return response()->json([
                'message' => 'Failed to reach tracking server'
            ], 500);
        }

        $position =
            $positionResponse->json()[0] ?? null;

        $device =
            $deviceResponse->json()[0] ?? null;

        if (!$position) {
            return response()->json([
                'message' => 'No position data yet for this vehicle'
            ], 404);
        }

        return response()->json([
            'vehicle_id' => $id,
            'latitude' => $position['latitude'],
            'longitude' => $position['longitude'],
            'speed' => $position['speed'],
            'gps_fix' => $position['valid'],
            'status' => $device['status'] ?? 'unknown',
            'last_updated' => $position['fixTime'],
        ]);
    }
}