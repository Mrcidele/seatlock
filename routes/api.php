<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\SeatMapController;
use App\Http\Controllers\Api\V1\TripController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('trips', [TripController::class, 'index'])->name('trips.index');
    Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');
    Route::get('trips/{trip}/seat-map', SeatMapController::class)->name('trips.seat-map');
});
