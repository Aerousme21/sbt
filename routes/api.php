<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SavedController;
use App\Http\Controllers\Api\Admin\AdminController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('/destinations', [DestinationController::class, 'index']);
Route::get('/destinations/featured', [DestinationController::class, 'featured']);
Route::get('/destinations/{id}', [DestinationController::class, 'show']);
Route::get('/categories', [DestinationController::class, 'categories']);
Route::get('/reviews/{destinationId}', [ReviewController::class, 'byDestination']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
    });

    Route::apiResource('trips', TripController::class);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::get('/my-reviews', [ReviewController::class, 'myReviews']);

    Route::get('/saved', [SavedController::class, 'index']);
    Route::post('/saved/toggle/{destinationId}', [SavedController::class, 'toggle']);

    // Admin routes
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/destinations', [AdminController::class, 'destinationIndex']);
        Route::post('/destinations', [AdminController::class, 'destinationStore']);
        Route::put('/destinations/{id}', [AdminController::class, 'destinationUpdate']);
        Route::delete('/destinations/{id}', [AdminController::class, 'destinationDestroy']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus']);
        Route::get('/reviews', [AdminController::class, 'reviews']);
        Route::delete('/reviews/{id}', [AdminController::class, 'deleteReview']);
    });
});
