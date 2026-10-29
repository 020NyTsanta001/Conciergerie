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
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\GameController;

Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('stats', StatsController::class);
    Route::get('properties', [VillaController::class, 'index']);
    Route::get('properties/{villa}', [VillaController::class, 'show']);
    Route::get('properties/{villa}/reviews', [ReviewController::class, 'index']);
    Route::get('yachts', [VillaController::class, 'yachts']);
    Route::get('services', [ServiceController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::patch('account/name', [AccountController::class, 'updateName']);
        Route::patch('account/password', [AccountController::class, 'updatePassword']);
        Route::delete('account', [AccountController::class, 'destroy']);
        Route::patch('account/currency', [AccountController::class, 'updateCurrency']);
        Route::get('wallet', [WalletController::class, 'show']);
        Route::post('wallet/claim', [WalletController::class, 'claim']);
        Route::post('games/pacman/start', [GameController::class, 'start'])->middleware('throttle:10,1');
        Route::post('games/pacman/{session}/finish', [GameController::class, 'finish'])->middleware('throttle:10,1');
        Route::post('bookings/{booking}/review', [ReviewController::class, 'store']);
        Route::put('reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);

        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings/quote', [BookingController::class, 'quote']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::patch('bookings/{booking}/status', [BookingController::class, 'status']);
        Route::post('bookings/{booking}/pay', [BookingController::class, 'pay']);
        Route::get('notifications', [BookingController::class, 'notifications']);
        Route::get('notifications/dots', [NotificationController::class, 'dots']);
        Route::post('notifications/read', [NotificationController::class, 'read']);

        Route::middleware(EnsureRole::class.':host')->prefix('host')->group(function () {
            Route::get('properties', [HostController::class, 'index']);
            Route::post('properties', [HostController::class, 'store']);
            Route::put('properties/{villa}', [HostController::class, 'update']);
            Route::delete('properties/{villa}', [HostController::class, 'destroy']);
            Route::get('payouts', [PayoutController::class, 'hostIndex']);
        });

        Route::middleware(EnsureRole::class.':admin')->prefix('admin')->group(function () {
            Route::get('properties', [AdminController::class, 'properties']);
            Route::patch('properties/{villa}/moderate', [AdminController::class, 'moderate']);
            Route::get('users', [AdminUserController::class, 'index']);
            Route::patch('users/{user}/suspend', [AdminUserController::class, 'suspend']);
            Route::get('activity', [AdminActivityController::class, 'index']);
            Route::delete('properties/{villa}', [AdminController::class, 'destroy']);
            Route::get('payouts', [PayoutController::class, 'adminIndex']);
            Route::get('messages', [AdminMessageController::class, 'index']);
            Route::patch('messages/{message}/read', [AdminMessageController::class, 'read']);
        });
    });
});
