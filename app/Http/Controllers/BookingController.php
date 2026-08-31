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
            ->whereIn('status', ['confirmed', 'ongoing'])
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
            'location' => $request->location,
            'duration' => $request->duration,
            'status' => 'pending',
            'total_price' => $totalPrice,
        ]);

        Notification::create([
            'user_id' => $request->user()->id,
            'booking_id' => $booking->id,
            'title' => 'Booking Submitted',
            'message' => 'Your booking has been submitted successfully and is awaiting approval.',
        ]);

        return response()->json([
            'message' => 'Booking created successfully',
            'booking' => $booking
        ], 201);
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

    public function updateCustomerBooking(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Customer can only edit their own booking
        abort_unless(
            $user->role === 'customer' &&
            $booking->user_id === $user->id,
            403,
            'You are not allowed to edit this booking.'
        );

        // Only pending bookings can be edited
        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be edited.'
            ], 422);
        }

        // Lock editing once any payment has been submitted
        if ($booking->payments()->exists()) {
            return response()->json([
                'message' => 'This booking can no longer be edited because a payment has already been submitted.'
            ], 422);
        }

        $validated = $request->validate([
            'car_id' => 'required|exists:cars,id',
            'pickup_date' => 'required|date',
            'return_date' => 'required|date|after:pickup_date',
            'location' => 'required|in:within,outside,unli',
            'duration' => 'required|in:12hrs,24hrs',
        ]);

        // Unli Mileage is valid for 24 hours only
        if (
            $validated['location'] === 'unli' &&
            $validated['duration'] !== '24hrs'
        ) {
            return response()->json([
                'message' => 'Unli Mileage is available for 24 hours only.'
            ], 422);
        }

        // Recheck vehicle availability, excluding this booking itself
        $conflictingBooking = Booking::where('car_id', $validated['car_id'])
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['confirmed', 'ongoing'])
            ->where(function ($query) use ($validated) {
                $query->where('pickup_date', '<', $validated['return_date'])
                    ->where('return_date', '>', $validated['pickup_date']);
            })
            ->exists();

        if ($conflictingBooking) {
            return response()->json([
                'message' => 'This vehicle is already reserved for the selected dates.'
            ], 422);
        }

        // Get the correct vehicle rate
        $rate = CarRate::where('car_id', $validated['car_id'])
            ->where('location', $validated['location'])
            ->where('duration', $validated['duration'])
            ->first();

        if (!$rate) {
            return response()->json([
                'message' => 'No pricing found for this booking.'
            ], 404);
        }

        // Recalculate total price on the server
        $pickup = Carbon::parse($validated['pickup_date']);
        $return = Carbon::parse($validated['return_date']);

        $hours = $pickup->diffInMinutes($return) / 60;

        if ($validated['duration'] === '12hrs') {
            $totalPrice = $rate->price;
        } else {
            $days = (int) ceil($hours / 24);

            if ($days < 1) {
                $days = 1;
            }

            $totalPrice = $rate->price * $days;
        }

        // Save edited booking
        $booking->update([
            'car_id' => $validated['car_id'],
            'pickup_date' => $validated['pickup_date'],
            'return_date' => $validated['return_date'],
            'location' => $validated['location'],
            'duration' => $validated['duration'],
            'total_price' => $totalPrice,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'title' => 'Booking Updated',
            'message' => 'Booking #' . $booking->id . ' has been updated successfully.',
        ]);

        return response()->json([
            'message' => 'Booking updated successfully.',
            'booking' => $booking->fresh('car'),
        ]);
    }

    public function cancelCustomerBooking(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Customer can only cancel their own booking
        abort_unless(
            $booking->user_id === $user->id,
            403,
            'You are not allowed to cancel this booking.'
        );

        // Only pending bookings can be cancelled
        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be cancelled.'
            ], 422);
        }

        // Lock cancellation once a payment has been submitted
        $hasPayment = $booking->payments()->exists();

        if ($hasPayment) {
            return response()->json([
                'message' => 'This booking can no longer be cancelled because a payment has already been submitted.'
            ], 422);
        }

        // Keep the booking record for history instead of deleting it
        $booking->update([
            'status' => 'cancelled',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'title' => 'Booking Cancelled',
            'message' => 'Booking #' . $booking->id . ' has been cancelled successfully.',
        ]);

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'booking' => $booking->fresh('car'),
        ]);
    }

    public function checkAvailability(Request $request)
    {
    $request->validate([
        'car_id' => 'required|exists:cars,id',
        'pickup_date' => 'required|date',
        'return_date' => 'required|date',
    ]);

    $reserved = Booking::where('car_id', $request->car_id)
        ->whereIn('status', ['confirmed', 'ongoing'])
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
