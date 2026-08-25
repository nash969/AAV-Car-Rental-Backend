<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
   public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'driver_license_number' => 'required|string|max:100',

            'password' => 'required|string|min:8|confirmed',

            'government_id' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'driver_license' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'selfie_id' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $governmentIdPath = $request->file('government_id')
            ->store('customer-documents/government-ids');

        $driverLicensePath = $request->file('driver_license')
            ->store('customer-documents/driver-licenses');

        $selfieIdPath = $request->file('selfie_id')
            ->store('customer-documents/selfies');

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'driver_license_number' => $request->driver_license_number,
            'government_id_path' => $governmentIdPath,
            'driver_license_path' => $driverLicensePath,
            'selfie_id_path' => $selfieIdPath,
            'password' => Hash::make($request->password),
            'role' => 'customer',
        ]);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user
        ], 201);
    }

     public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password'
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $user->createToken('aav-web')->plainTextToken,
        ]);
    }

    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $otp = random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            [
                'email' => $request->email
            ],
            [
                'token' => Hash::make((string) $otp),
                'created_at' => now()
            ]
        );

        Mail::raw(
            "Your AAV Car Rental Services password reset OTP is: {$otp}\n\n" .
            "This code will expire in 10 minutes.",
            function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('AAV Car Rental Services - Password Reset OTP');
            }
        );

        return response()->json([
            'message' => 'OTP sent successfully to your email.'
        ]);
    }

    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'No OTP request found for this email.'
            ], 404);
        }

        // OTP valid for 10 minutes
        if (now()->diffInMinutes($record->created_at) > 10) {
            return response()->json([
                'message' => 'OTP has expired. Please request a new one.'
            ], 422);
        }

        if (!Hash::check((string) $request->otp, $record->token)) {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 422);
        }

        return response()->json([
            'message' => 'OTP verified successfully.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'message' => 'Password reset successfully.'
        ]);
    }

    public function customers()
    {
        $customers = User::where('role', 'customer')
            ->latest()
            ->get();

        return response()->json($customers);
    }

    public function customerRequirements(Request $request, User $user)
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Only administrators can view customer requirements.'
        );

        abort_unless(
            $user->role === 'customer',
            404,
            'Customer not found.'
        );

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'driver_license_number' => $user->driver_license_number,
            'verification_status' => $user->verification_status,
            'government_id_path' => $user->government_id_path,
            'driver_license_path' => $user->driver_license_path,
            'selfie_id_path' => $user->selfie_id_path,
        ]);
    }

    public function customerDocument(Request $request, User $user, $type)
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Only administrators can view customer documents.'
        );

        abort_unless(
            $user->role === 'customer',
            404,
            'Customer not found.'
        );

        $documents = [
            'government-id' => $user->government_id_path,
            'driver-license' => $user->driver_license_path,
            'selfie-id' => $user->selfie_id_path,
        ];

        if (!array_key_exists($type, $documents)) {
            abort(404, 'Document type not found.');
        }

        $path = $documents[$type];

        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'Document file not found.');
        }

        return Storage::disk('local')->response($path);
    }

    public function reviewCustomerVerification(Request $request, User $user)
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Only administrators can review customer verification.'
        );

        abort_unless(
            $user->role === 'customer',
            404,
            'Customer not found.'
        );

        $request->validate([
            'status' => 'required|in:verified,rejected',
        ]);

        $user->update([
            'verification_status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Customer verification updated successfully.',
            'user' => $user,
        ]);
    }
}
