<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Notification;

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
        abort_unless($booking->user_id === $request->user()->id, 403, 'You can only submit proof for your own booking.');

        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'Only pending bookings can receive a payment confirmation.'], 422);
        }

        $hasOpenPayment = Payment::where('booking_id', $booking->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($hasOpenPayment) {
            return response()->json(['message' => 'This booking already has a payment awaiting review or approved.'], 422);
        }

        $proofPath = $request->file('proof')->store('payment-proofs');

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
        $mayView = $user->role === 'admin' || $payment->submitted_by === $user->id;
        abort_unless($mayView, 403, 'You are not allowed to view this payment proof.');
        abort_unless(Storage::exists($payment->proof_path), 404, 'Payment proof was not found.');

        return Storage::response($payment->proof_path);
    }
}
