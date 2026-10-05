<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BoardingController;
use App\Http\Controllers\Api\V1\DevPaymentController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\SeatLockController;
use App\Http\Controllers\Api\V1\SeatMapController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->middleware('throttle:api')->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    });

    Route::post('webhooks/{provider}', WebhookController::class)
        ->middleware('throttle:webhooks')
        ->withoutMiddleware('throttle:api')
        ->name('webhooks');

    Route::get('trips', [TripController::class, 'index'])->name('trips.index');
    Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');
    Route::get('trips/{trip}/seat-map', SeatMapController::class)->name('trips.seat-map');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::middleware('throttle:seat-locks')->group(function (): void {
            Route::post('trips/{trip}/locks', [SeatLockController::class, 'store'])->name('trips.locks.store');
            Route::delete('trips/{trip}/locks', [SeatLockController::class, 'destroy'])->name('trips.locks.destroy');
        });

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

        Route::middleware('throttle:orders')->group(function (): void {
            Route::post('orders', [OrderController::class, 'store'])->middleware('idempotent')->name('orders.store');
            Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('orders/{order}/renew', [OrderController::class, 'renew'])->name('orders.renew');
            Route::post('orders/{order}/rebook', [OrderController::class, 'rebook'])->name('orders.rebook');
            Route::post('orders/{order}/payments', [PaymentController::class, 'store'])->middleware('idempotent')->name('orders.payments.store');
        });

        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::get('tickets/{ticket}/pdf', [TicketController::class, 'pdf'])->name('tickets.pdf');
        Route::post('boarding/validate', BoardingController::class)->name('boarding.validate');
        Route::get('admin/metrics', MetricsController::class)->name('admin.metrics');

        if (app()->environment('local', 'testing')) {
            Route::post('dev/payments/{payment}/simulate', DevPaymentController::class)->name('dev.payments.simulate');
        }
    });
});
