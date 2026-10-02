<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\VillaController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminActivityController;
use App\Http\Controllers\AdminUserController;

Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('stats', StatsController::class);
    Route::get('properties', [VillaController::class, 'index']);
    Route::get('properties/{villa}', [VillaController::class, 'show']);
    Route::get('seasons', [VillaController::class, 'seasons']);
    Route::get('services', [ServiceController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::patch('account/name', [AccountController::class, 'updateName']);
        Route::patch('account/password', [AccountController::class, 'updatePassword']);
        Route::delete('account', [AccountController::class, 'destroy']);

        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings/quote', [BookingController::class, 'quote']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::patch('bookings/{booking}/status', [BookingController::class, 'status']);
        Route::post('bookings/{booking}/pay', [BookingController::class, 'pay']);
        Route::get('notifications', [BookingController::class, 'notifications']);

        Route::middleware(EnsureRole::class.':host,admin')->prefix('host')->group(function () {
            Route::get('properties', [HostController::class, 'index']);
            Route::post('properties', [HostController::class, 'store']);
            Route::put('properties/{villa}/seasons', [HostController::class, 'syncSeasons']);
            
        });

        Route::middleware(EnsureRole::class.':admin')->prefix('admin')->group(function () {
            Route::get('properties', [AdminController::class, 'properties']);
            Route::patch('properties/{villa}/moderate', [AdminController::class, 'moderate']);
            Route::get('users', [AdminUserController::class, 'index']);
            Route::patch('users/{user}/suspend', [AdminUserController::class, 'suspend']);
            Route::get('activity', [AdminActivityController::class, 'index']);
        });
    });
});
