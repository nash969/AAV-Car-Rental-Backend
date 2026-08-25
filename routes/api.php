<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\TrackingController;

Route::apiResource('cars', CarController::class);
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('forgot-password/send-otp', [AuthController::class, 'sendResetOtp']);
Route::post('forgot-password/verify-otp', [AuthController::class, 'verifyResetOtp']);
Route::post('forgot-password/reset', [AuthController::class, 'resetPassword']);
Route::apiResource('bookings', BookingController::class)->only(['index', 'show']);
Route::get('tracking/devices', [TrackingController::class, 'devices']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('bookings', [BookingController::class, 'store']);
    Route::patch('bookings/{booking}', [BookingController::class, 'update']);
    Route::patch('bookings/{booking}/rental-status', [BookingController::class, 'updateRentalStatus']);
    Route::get('users/{user_id}/bookings', [BookingController::class, 'userBookings']);
    Route::get('my/bookings', [BookingController::class, 'myBookings']);
    Route::get('customers', [AuthController::class, 'customers']);
    Route::get('customers/{user}/requirements', [AuthController::class, 'customerRequirements']);
    Route::get('customers/{user}/documents/{type}', [AuthController::class, 'customerDocument']);
    Route::patch('customers/{user}/verification', [AuthController::class, 'reviewCustomerVerification']);
    Route::post('bookings/check-availability', [BookingController::class, 'checkAvailability']);
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store']);
    Route::patch('payments/{payment}/review', [PaymentController::class, 'review']);
    Route::get('payments/{payment}/proof', [PaymentController::class, 'proof']);
    Route::get('/notifications/{userId}', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::post('/chat/log', [ChatController::class, 'logLocal']);
    Route::get('/chat-logs', [ChatController::class, 'logs']);
});
