<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PondController;
use App\Http\Controllers\Api\V1\ReadingIngestController;
use Illuminate\Support\Facades\Route;

Route::middleware('device.auth')->prefix('v1')->group(function () {
    Route::post('/readings', [ReadingIngestController::class, 'store'])->name('api.v1.readings.store');
});

// Autenticación por token Sanctum para el frontend React.
Route::prefix('v1/auth')->name('api.v1.auth.')->group(function () {
    // Públicas: no necesitan token.
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');

    // Protegidas: exigen Authorization: Bearer <token>.
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/me', [AuthController::class, 'update'])->name('update');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

// CRUD de estanques consumido por el frontend React (con token).
Route::middleware(['auth:sanctum', 'active'])->prefix('v1/ponds')->name('api.v1.ponds.')->group(function () {
    Route::get('/options', [PondController::class, 'options'])->name('options');
    Route::get('/', [PondController::class, 'index'])->name('index');
    Route::get('/{pond}', [PondController::class, 'show'])->name('show');
    Route::post('/', [PondController::class, 'store'])->name('store');
    Route::put('/{pond}', [PondController::class, 'update'])->name('update');
    Route::patch('/{pond}/activar', [PondController::class, 'activate'])->name('activate');
    Route::patch('/{pond}/desactivar', [PondController::class, 'deactivate'])->name('deactivate');
});
