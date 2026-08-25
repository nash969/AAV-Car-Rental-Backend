<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TrackingController extends Controller
{
    public function devices()
    {
        $response = Http::withBasicAuth(
            env('TRACCAR_EMAIL'),
            env('TRACCAR_PASSWORD')
        )->get('http://localhost:8082/api/devices');

        if ($response->failed()) {
            return response()->json([
                'message' => 'Unable to connect to Traccar.'
            ], 500);
        }

        return response()->json($response->json());
    }
}