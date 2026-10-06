<?php

use App\Http\Controllers\Auth\AccountRecoveryController;
use App\Http\Controllers\Auth\NewPasswordAfterRecoveryController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->prefix('recuperar-cuenta')->name('recovery.')->group(function () {
    Route::get('/', [AccountRecoveryController::class, 'create'])->name('request');
    Route::post('/', [AccountRecoveryController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('send');

    Route::get('/verificar', [AccountRecoveryController::class, 'verify'])->name('verify');
    Route::post('/reenviar', [AccountRecoveryController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('otp.resend');

    Route::post('/verificar/otp', [AccountRecoveryController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')
        ->name('otp.verify');
    Route::post('/verificar/codigo', [AccountRecoveryController::class, 'verifyCode'])
        ->middleware('throttle:10,1')
        ->name('code.verify');
    Route::post('/verificar/archivo', [AccountRecoveryController::class, 'verifyFile'])
        ->middleware('throttle:10,1')
        ->name('file.verify');

    Route::get('/nueva-contrasena', [NewPasswordAfterRecoveryController::class, 'edit'])->name('password.edit');
    Route::post('/nueva-contrasena', [NewPasswordAfterRecoveryController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});
