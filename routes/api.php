<?php

use App\Http\Controllers\Api\V1\ReadingIngestController;
use Illuminate\Support\Facades\Route;

Route::middleware('device.auth')->prefix('v1')->group(function () {
    Route::post('/readings', [ReadingIngestController::class, 'store'])->name('api.v1.readings.store');
});
