<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProducerController;
use App\Http\Controllers\ProducerStatusController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPasswordResetLinkController;
use App\Http\Controllers\UserStatusController;
use App\Models\Producer;
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

    Route::prefix('productores')->name('producers.')->group(function () {
        Route::middleware('can:manage-producers')->group(function () {
            Route::get('/crear', [ProducerController::class, 'create'])->name('create');
            Route::post('/', [ProducerController::class, 'store'])->name('store');
            Route::get('/{producer}/editar', [ProducerController::class, 'edit'])->name('edit');
            Route::put('/{producer}', [ProducerController::class, 'update'])->name('update');

            Route::get('/{producer}/desactivar', [ProducerStatusController::class, 'confirmDeactivation'])->name('deactivate.confirm');
            Route::patch('/{producer}/desactivar', [ProducerStatusController::class, 'deactivate'])->name('deactivate');
            Route::patch('/{producer}/activar', [ProducerStatusController::class, 'activate'])->name('activate');
        });

        Route::get('/', [ProducerController::class, 'index'])->middleware('can:viewAny,'.Producer::class)->name('index');
        Route::get('/{producer}', [ProducerController::class, 'show'])->middleware('can:view,producer')->name('show');
    });
});

require __DIR__.'/auth.php';
