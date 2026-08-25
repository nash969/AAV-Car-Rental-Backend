<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Car;
use App\Models\AiChatLog;

class ChatController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $cars = Car::with('rates')->get();

        $carContext = $cars->map(function ($car) {

        $rates = $car->rates->map(function ($rate) {
            return ucfirst($rate->location)
                . " Manila - "
                . $rate->duration
                . ": ₱"
                . number_format($rate->price, 2);
    })->implode(", ");

    return
        $car->brand . " " . $car->model
        . " | Seats: " . $car->seats
        . " | Transmission: " . $car->transmission
        . " | Fuel: " . $car->fuel_type
        . " | Availability: " . ($car->available ? "Available" : "Reserved")
        . " | Rates: " . $rates;

        })->implode("\n");

        $businessContext = "
        AAV Car Rental Services business information:

        Office Location:
        46 Pag-asa St., Brgy. Katuparan, Taguig City

        Pickup Location:
        46 Pag-asa St., Brgy. Katuparan, Taguig City

        Return Location:
        46 Pag-asa St., Brgy. Katuparan, Taguig City

        Payment Methods:
        - GCash
        - Bank Transfer

        Reservation Fee:
        ₱500 per rental day.
        The reservation fee is deductible from the total rental cost.

        Cancellation Policy:
        The reservation fee is non-refundable if the customer cancels the reservation.

        Booking Process:
        1. Create or log in to a customer account.
        2. Go to Browse Vehicles.
        3. Choose an available vehicle and click Book Now.
        4. Enter the pickup date and time, return date and time, and trip destination.
        5. Review the rental duration, reservation fee, and total rental cost.
        6. Submit the booking.
        7. Go to Payments and pay the reservation fee through GCash or Bank Transfer.
        8. Upload the payment receipt or proof of payment.
        9. Wait for the admin to verify the payment and approve the reservation.
        10. Once approved, the customer will receive a notification and the booking status will be updated.

        Rental Extension:
        Customers who want to extend their rental period must contact AAV Car Rental Services before the original return schedule. The extension is subject to vehicle availability and additional rental charges.

        Late Return:
        Customers should return the vehicle on or before the agreed return date and time. If the vehicle will be returned late, the customer must inform AAV Car Rental Services as soon as possible. Additional charges may apply depending on the delay.

        Booking Changes:
        Customers who want to change their pickup date, return date, or other booking details must contact AAV Car Rental Services. Changes are subject to vehicle availability and approval.

        Authorized Driver:
        Only the customer or an authorized driver approved by AAV Car Rental Services should operate the rented vehicle.

        Vehicle Problems:
        If the rented vehicle develops a problem during the rental period, the customer should stop in a safe location when possible and contact AAV Car Rental Services for assistance.

        Required Documents:
        Customers must provide the required verification documents requested by AAV Car Rental Services, including a valid driver's license and valid identification before the booking is fully approved.
        ";

        $response = Http::withHeaders([
            'x-goog-api-key' => env('GEMINI_API_KEY'),
            'Content-Type' => 'application/json'
        ])->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                "text" =>
                                "You are the AI assistant of AAV Car Rental Services.

                                Use ONLY the vehicle and business information provided below.
                                Do NOT invent information that is not provided.

                                Vehicle Information:
                                {$carContext}

                                Business Information:
                                {$businessContext}

                                Rules:
                                - Only answer questions related to AAV Car Rental.
                                - If the user asks about available vehicles, only mention the vehicles listed above.
                                - If the user asks about prices, use only the rates listed above.
                                - If the user asks about the office location, payment methods, reservation fee, or cancellation policy, use the Business Information above.
                                - If you don't know something from the provided data, politely say that the information is unavailable.

                                Customer Question:
                                {$request->message}"
                            ]
                        ]
                    ]
                ]
            ]
        );

        if ($response->failed()) {
            return response()->json([
                'message' => 'Gemini API request failed.',
                'status' => $response->status(),
                'error' => $response->json(),
            ], $response->status());
        }

        $aiText = data_get(
            $response->json(),
            'candidates.0.content.parts.0.text'
        );

        if ($aiText) {
            AiChatLog::create([
                'user_id' => $request->user()?->id,
                'message' => $request->message,
                'response' => $aiText,
                'source' => 'AI',
            ]);
        }

        return response()->json($response->json());
    }

    public function logLocal(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'response' => 'required|string',
        ]);

        $log = AiChatLog::create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'response' => $validated['response'],
            'source' => 'Local',
        ]);

        return response()->json([
            'message' => 'Local chat logged successfully.',
            'data' => $log
        ], 201);
    }

    public function logs(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'employee'])) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        return AiChatLog::with('user')
            ->latest()
            ->take(100)
            ->get();
    }
}