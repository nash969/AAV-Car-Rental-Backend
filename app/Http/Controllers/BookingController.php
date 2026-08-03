<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\CarRate;
use Carbon\Carbon;
use App\Models\Notification;

class BookingController extends Controller
{
    // Display all bookings
    public function index()
    {
        $bookings = Booking::with(['user', 'car'])->get();

        return response()->json($bookings);
    }


    // Create a new booking
    public function store(Request $request)
    {
        $request->validate([
            'car_id' => 'required|exists:cars,id',
            'pickup_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:pickup_date',

            'location' => 'required|in:within,outside',
            'duration' => 'required|in:12hrs,24hrs',
        ]);

        $existingBooking = Booking::where('car_id', $request->car_id)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($request) {
                $query->where('pickup_date', '<', $request->return_date)
                    ->where('return_date', '>', $request->pickup_date);
            })
            ->exists();

        if ($existingBooking) {
            return response()->json([
                'message' => 'This vehicle is already reserved for the selected dates.'
            ], 422);
        }

        $rate = CarRate::where('car_id', $request->car_id)
            ->where('location', $request->location)
            ->where('duration', $request->duration)
            ->first();

        if (!$rate) {
            return response()->json([
                'message' => 'No pricing found for this booking.'
            ], 404);
        }

        $days = Carbon::parse($request->pickup_date)
            ->diffInDays(Carbon::parse($request->return_date));

        if ($days < 1) {
            $days = 1;
        }

        if ($request->duration === '12hrs') {
            $totalPrice = $rate->price;
        } else {
            $totalPrice = $rate->price * $days;
        }


        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'car_id' => $request->car_id,
            'pickup_date' => $request->pickup_date,
            'return_date' => $request->return_date,
            'status' => 'pending',
            'total_price' => $totalPrice,
        ]);

        Notification::create([
            'user_id' => $request->user()->id,
            'title' => 'Booking Submitted',
            'message' => 'Your booking has been submitted successfully and is awaiting approval.',
        ]);

        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => $booking
        ], 201);
    }

    // Update booking status (Admin)
    public function update(Request $request, Booking $booking)
    {
        abort_unless($request->user()->role === 'admin', 403, 'Only administrators can update booking status.');

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $booking->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => $booking,
        ]);
    }

    // Display bookings of a specific customer
    public function userBookings(Request $request, $user_id)
    {
        abort_unless($request->user()->role === 'admin' || $request->user()->id === (int) $user_id, 403);

        $bookings = Booking::with('car')
            ->where('user_id', $user_id)
            ->get();

        return response()->json($bookings);
    }

    public function myBookings(Request $request)
    {
        return response()->json(
            Booking::with('car')->where('user_id', $request->user()->id)->latest()->get()
        );
    }

    public function checkAvailability(Request $request)
    {
    $request->validate([
        'car_id' => 'required|exists:cars,id',
        'pickup_date' => 'required|date',
        'return_date' => 'required|date',
    ]);

    $reserved = Booking::where('car_id', $request->car_id)
        ->where('status', 'confirmed')
        ->where(function ($query) use ($request) {
            $query->where('pickup_date', '<', $request->return_date)
                  ->where('return_date', '>', $request->pickup_date);
        })
        ->exists();

        return response()->json([
            'available' => !$reserved
        ]);
    }
}   
