<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Notification;
use Carbon\Carbon;
use App\Models\SystemSetting;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Payment::with(['booking.car', 'submitter', 'reviewer'])->latest();

        if ($user->role === 'customer') {
            $query->where('submitted_by', $user->id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'method' => 'required|in:gcash,bank_transfer',
            'amount' => 'required|numeric|min:1|max:999999.99',
            'payer_name' => 'required|string|max:255',
            'reference_number' => 'required|string|max:100',
            'paid_at' => 'required|date|before_or_equal:now',
            'customer_confirmed' => 'accepted',
            'proof' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        abort_unless(
            $booking->user_id === $request->user()->id,
            403,
            'You can only submit proof for your own booking.'
        );

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'This booking cannot receive another payment.'
            ], 422);
        }

        $hasOpenPayment = Payment::where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasOpenPayment) {
            return response()->json([
                'message' => 'This booking already has a payment awaiting review.'
            ], 422);
        }

        // Total of all approved payments for this booking
        $approvedPaid = Payment::where('booking_id', $booking->id)
            ->where('status', 'approved')
            ->sum('amount');

        // Remaining unpaid balance
        $remainingBalance = max(
            0,
            (float) $booking->total_price - (float) $approvedPaid
        );

        $pickup = Carbon::parse($booking->pickup_date);
        $return = Carbon::parse($booking->return_date);

        $hours = $pickup->diffInMinutes($return) / 60;

        $reservationDays = max(
            1,
            (int) ceil($hours / 24)
        );

        $settings = SystemSetting::first();

        $reservationFeePerDay = (float) ($settings?->reservation_fee ?? 500);

        $requiredReservationFee =
            $reservationDays * $reservationFeePerDay;

        if ((float) $approvedPaid < $requiredReservationFee) {
            if ((float) $validated['amount'] < $requiredReservationFee) {
                return response()->json([
                    'message' => 'The minimum reservation payment for this booking is ₱' .
                        number_format($requiredReservationFee, 2) . '.'
                ], 422);
            }
        }

        // Prevent payment when booking is already fully paid
        if ($remainingBalance <= 0) {
            return response()->json([
                'message' => 'This booking is already fully paid.'
            ], 422);
        }

        // Prevent overpayment
        if ((float) $validated['amount'] > $remainingBalance) {
            return response()->json([
                'message' => 'Payment cannot exceed the remaining balance of ₱' .
                    number_format($remainingBalance, 2) . '.'
            ], 422);
        }

        $proofUpload = cloudinary()->uploadApi()->upload(
            $request->file('proof')->getRealPath(),
            [
                'folder' => 'aav-car-rental/payment-proofs',
                'type' => 'authenticated',
            ]
        );

        $proofPath = $proofUpload['public_id'];

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'submitted_by' => $request->user()->id,
            'method' => $validated['method'],
            'amount' => $validated['amount'],
            'payer_name' => $validated['payer_name'],
            'reference_number' => $validated['reference_number'],
            'paid_at' => $validated['paid_at'],
            'proof_path' => $proofPath,
            'status' => 'pending',
            'customer_confirmed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Payment confirmation sent for admin review.',
            'payment' => $payment->load(['booking.car', 'submitter']),
        ], 201);
    }

    public function storeCash(Request $request)
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Only administrators can record cash payments.'
        );

        $validated = $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'amount' => 'required|numeric|min:1|max:999999.99',
            'payer_name' => 'required|string|max:255',
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        $hasOpenPayment = Payment::where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasOpenPayment) {
            return response()->json([
                'message' => 'This booking has a payment awaiting review. Please review it first before recording cash.'
            ], 422);
        }

        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'This booking cannot receive another payment.'
            ], 422);
        }

        $approvedPaid = Payment::where('booking_id', $booking->id)
            ->where('status', 'approved')
            ->sum('amount');
        
        $pickup = Carbon::parse($booking->pickup_date);
        $return = Carbon::parse($booking->return_date);

        $hours = $pickup->diffInMinutes($return) / 60;

        $reservationDays = max(
            1,
            (int) ceil($hours / 24)
        );

        $settings = SystemSetting::first();

        $reservationFeePerDay = (float) ($settings?->reservation_fee ?? 500);

        $requiredReservationFee =
            $reservationDays * $reservationFeePerDay;

        if ((float) $approvedPaid < $requiredReservationFee) {
            return response()->json([
                'message' => 'The required reservation fee must be approved first before recording the remaining balance in cash.'
            ], 422);
        }

        $remainingBalance = max(
            0,
            (float) $booking->total_price - (float) $approvedPaid
        );

        if ($remainingBalance <= 0) {
            return response()->json([
                'message' => 'This booking is already fully paid.'
            ], 422);
        }

        if ((float) $validated['amount'] > $remainingBalance) {
            return response()->json([
                'message' => 'Cash payment cannot exceed the remaining balance of ₱' .
                    number_format($remainingBalance, 2) . '.'
            ], 422);
        }

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'submitted_by' => $booking->user_id,
            'method' => 'cash',
            'amount' => $validated['amount'],
            'payer_name' => $validated['payer_name'],
            'reference_number' => null,
            'paid_at' => now(),
            'proof_path' => null,
            'status' => 'approved',
            'customer_confirmed_at' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => 'Cash payment received and recorded by administrator.',
        ]);

        Notification::create([
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'title' => 'Cash Payment Recorded',
            'message' => 'A cash payment of ₱' .
                number_format((float) $validated['amount'], 2) .
                ' has been recorded for Booking #' . $booking->id . '.',
        ]);

        return response()->json([
            'message' => 'Cash payment recorded successfully.',
            'payment' => $payment->load(['booking.car', 'submitter', 'reviewer']),
        ], 201);
    }

    public function review(Request $request, Payment $payment)
    {
        abort_unless($request->user()->role === 'admin', 403, 'Only administrators can review payments.');

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'review_note' => 'nullable|string|max:1000',
        ]);

        if ($validated['status'] === 'rejected' && blank($validated['review_note'] ?? null)) {
            return response()->json(['message' => 'Please explain why the payment was rejected.'], 422);
        }

        $payment = DB::transaction(function () use ($payment, $validated, $request) {
            $lockedPayment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== 'pending') {
                abort(422, 'This payment has already been reviewed.');
            }

            if ($validated['status'] === 'approved') {

                $booking = $lockedPayment->booking;

                $conflictingBooking = Booking::where('car_id', $booking->car_id)
                    ->where('id', '!=', $booking->id)
                    ->whereIn('status', ['confirmed', 'ongoing'])
                    ->where(function ($query) use ($booking) {
                        $query->where('pickup_date', '<', $booking->return_date)
                            ->where('return_date', '>', $booking->pickup_date);
                    })
                    ->exists();

                if ($conflictingBooking) {
                    abort(
                        422,
                        'This vehicle is already reserved for the selected dates. The payment cannot be approved.'
                    );
                }
            }

            $lockedPayment->update([
                'status' => $validated['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
            ]);

            if ($validated['status'] === 'approved') {

                $booking = $lockedPayment->booking;

                $booking->update([
                    'status' => 'confirmed',
                ]);
            }

            return $lockedPayment;
        });

        Notification::create([
            'user_id' => $payment->submitted_by,
            'booking_id' => $payment->booking_id,
            'payment_id' => $payment->id,

            'title' => $payment->status === 'approved'
                ? 'Payment Approved'
                : 'Payment Rejected',

            'message' => $payment->status === 'approved'
                ? 'Your payment has been approved and your booking is now confirmed.'
                : 'Your payment has been rejected. Reason: ' . ($payment->review_note ?? 'No reason provided.'),
        ]);

        return response()->json([
            'message' => 'Payment '.$payment->status.'.',
            'payment' => $payment->load(['booking.car', 'submitter', 'reviewer']),
        ]);
    }

    public function proof(Request $request, Payment $payment)
    {
        $user = $request->user();

        $mayView =
            in_array($user->role, ['admin', 'employee']) ||
            $payment->submitted_by === $user->id;

        abort_unless(
            $mayView,
            403,
            'You are not allowed to view this payment proof.'
        );

        if (blank($payment->proof_path)) {
            return response()->json([
                'message' => 'No payment proof is required for this payment.'
            ], 404);
        }

        $url = cloudinary()->image($payment->proof_path)
            ->deliveryType('authenticated')
            ->signUrl(true)
            ->toUrl();

        return redirect()->away($url);
    }
}
