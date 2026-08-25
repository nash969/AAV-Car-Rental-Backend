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
        $user = $request->user();

        if ($user->verification_status !== 'verified') {

            $message = match ($user->verification_status) {
                'rejected' => 'Your account verification was rejected. Please contact AAV Car Rental Services for assistance.',
                default => 'Your account is still pending verification. Please wait for admin approval before making a booking.',
            };

            return response()->json([
                'message' => $message
            ], 403);
        }
        
        $request->validate([
            'car_id' => 'required|exists:cars,id',
            'pickup_date' => 'required|date',
            'return_date' => 'required|date|after:pickup_date',

            'location' => 'required|in:within,outside,unli',
            'duration' => 'required|in:12hrs,24hrs',
        ]);

        if ($request->location === 'unli' && $request->duration !== '24hrs') {
            return response()->json([
                'message' => 'Unli Mileage is available for 24 hours only.'
            ], 422);
        }

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

        $pickup = Carbon::parse($request->pickup_date);
        $return = Carbon::parse($request->return_date);

        $hours = $pickup->diffInMinutes($return) / 60;

        if ($request->duration === '12hrs') {

            $totalPrice = $rate->price;

        } else {

            $days = (int) ceil($hours / 24);

            if ($days < 1) {
                $days = 1;
            }

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

    public function updateRentalStatus(Request $request, Booking $booking)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'employee']),
            403,
            'Only administrators or employees can update rental status.'
        );

        $request->validate([
            'status' => 'required|in:ongoing,completed',
        ]);

        if ($request->status === 'ongoing' && $booking->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed bookings can be started as rentals.'
            ], 422);
        }

        if ($request->status === 'completed' && $booking->status !== 'ongoing') {
            return response()->json([
                'message' => 'Only ongoing rentals can be completed.'
            ], 422);
        }

        $booking->update([
            'status' => $request->status,
        ]);

        if ($request->status === 'ongoing') {
            $booking->car()->update([
                'available' => false,
            ]);
        }

        if ($request->status === 'completed') {
            $booking->car()->update([
                'available' => true,
            ]);
        }

        return response()->json([
            'message' => 'Rental status updated successfully.',
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
