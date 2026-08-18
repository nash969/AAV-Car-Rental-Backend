<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TraccarController extends Controller
{
    // Maps our internal vehicle IDs to Traccar's device IDs.
    // Replace with a real DB column on the cars table later
    // (e.g. cars.traccar_device_id) instead of hardcoding this.
    private array $vehicleToDevice = [
        1 => 1, // vehicle id 1 -> Traccar device id 1 (Test Car)
    ];

    public function location(int $id)
    {
        $deviceId = $this->vehicleToDevice[$id] ?? null;

        if (!$deviceId) {
            return response()->json(['message' => 'Unknown vehicle ID'], 404);
        }

        $traccarUrl = config('services.traccar.url');
        $auth = [config('services.traccar.email'), config('services.traccar.password')];

        $positionResponse = Http::withBasicAuth(...$auth)
            ->get("{$traccarUrl}/api/positions", ['deviceId' => $deviceId]);

        $deviceResponse = Http::withBasicAuth(...$auth)
            ->get("{$traccarUrl}/api/devices", ['id' => $deviceId]);

       if ($positionResponse->failed() || $deviceResponse->failed()) {
            return response()->json(['message' => 'Failed to reach tracking server'], 500);
        }

        $position = $positionResponse->json()[0] ?? null;
        $device = $deviceResponse->json()[0] ?? null;

        if (!$position) {
            return response()->json(['message' => 'No position data yet for this vehicle'], 404);
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
