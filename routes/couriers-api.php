<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CouriersApiController;

/*
|--------------------------------------------------------------------------
| Couriers API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('/{courier}')->group(function () {
    Route::post('/update-tracking', [CouriersApiController::class, 'updateTracking'])->name('couriersApi.updateTracking');
});
