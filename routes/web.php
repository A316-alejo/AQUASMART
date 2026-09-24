<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPasswordResetLinkController;
use App\Http\Controllers\UserStatusController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:manage-users')->prefix('usuarios')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/crear', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');

        Route::get('/{user}/desactivar', [UserStatusController::class, 'confirmDeactivation'])->name('deactivate.confirm');
        Route::patch('/{user}/desactivar', [UserStatusController::class, 'deactivate'])->name('deactivate');
        Route::patch('/{user}/activar', [UserStatusController::class, 'activate'])->name('activate');

        Route::post('/{user}/enlace-contrasena', [UserPasswordResetLinkController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('password-link');
    });
});

require __DIR__.'/auth.php';
