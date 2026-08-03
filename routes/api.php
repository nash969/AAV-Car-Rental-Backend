<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CarController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Api\NotificationController;

Route::apiResource('cars', CarController::class);
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::apiResource('bookings', BookingController::class)->only(['index', 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('bookings', [BookingController::class, 'store']);
    Route::patch('bookings/{booking}', [BookingController::class, 'update']);
    Route::get('users/{user_id}/bookings', [BookingController::class, 'userBookings']);
    Route::get('my/bookings', [BookingController::class, 'myBookings']);
    Route::post('bookings/check-availability', [BookingController::class, 'checkAvailability']);
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store']);
    Route::patch('payments/{payment}/review', [PaymentController::class, 'review']);
    Route::get('payments/{payment}/proof', [PaymentController::class, 'proof']);
    Route::get('/notifications/{userId}', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
});
