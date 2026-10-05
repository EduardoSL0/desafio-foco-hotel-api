<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilitySearchController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\HotelReportController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ReserveController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\Idempotency;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1  (prefixo /api/v1 definido em bootstrap/app.php)
|--------------------------------------------------------------------------
*/

// Autenticação
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

// Rotas públicas (motor de reservas)
Route::middleware('throttle:public')->group(function () {
    Route::get('hotels', [HotelController::class, 'index'])->name('hotels.index');
    Route::get('hotels/{hotel}', [HotelController::class, 'show'])->name('hotels.show');

    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::get('rooms/{room}/availability', [RoomController::class, 'availability'])->name('rooms.availability');

    Route::get('availability', AvailabilitySearchController::class)->name('availability.search');
});

// "Minha reserva": limite baixo de tentativas contra adivinhação de localizadores.
Route::post('reserves/lookup', [ReserveController::class, 'lookup'])->middleware('throttle:lookup')->name('reserves.lookup');

Route::middleware('throttle:booking')->group(function () {
    Route::post('reserves/quote', [ReserveController::class, 'quote'])->name('reserves.quote');
    Route::post('reserves', [ReserveController::class, 'store'])->middleware(Idempotency::class)->name('reserves.store');
});

// Rotas autenticadas (gestão do hoteleiro) - Authorization: Bearer <token>
Route::middleware(['auth:sanctum', 'throttle:staff'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('hotels/{hotel}/report', HotelReportController::class)->name('hotels.report');

    Route::post('rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::match(['put', 'patch'], 'rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

    Route::get('reserves', [ReserveController::class, 'index'])->name('reserves.index');
    Route::get('reserves/{reserve}', [ReserveController::class, 'show'])->name('reserves.show');
    Route::patch('reserves/{reserve}/cancel', [ReserveController::class, 'cancel'])->name('reserves.cancel');

    Route::get('reserves/{reserve}/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('reserves/{reserve}/payments', [PaymentController::class, 'store'])->middleware(Idempotency::class)->name('payments.store');
    Route::post('reserves/{reserve}/payments/{payment}/refund', [PaymentController::class, 'refund'])
        ->scopeBindings()->middleware(Idempotency::class)->name('payments.refund');

    Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');

    Route::apiResource('promotions', PromotionController::class);
    Route::apiResource('users', UserController::class);
});
