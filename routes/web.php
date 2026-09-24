<?php

use App\Enums\Catalog;
use App\Http\Controllers\BatchClosureController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogStatusController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FarmAssignmentController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\FarmStatusController;
use App\Http\Controllers\PondController;
use App\Http\Controllers\PondStatusController;
use App\Http\Controllers\ProducerController;
use App\Http\Controllers\ProducerStatusController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPasswordResetLinkController;
use App\Http\Controllers\UserStatusController;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Pond;
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

    Route::prefix('fincas')->name('farms.')->group(function () {
        Route::middleware('can:manage-farms')->group(function () {
            Route::get('/crear', [FarmController::class, 'create'])->name('create');
            Route::post('/', [FarmController::class, 'store'])->name('store');
            Route::get('/{farm}/editar', [FarmController::class, 'edit'])->name('edit');
            Route::put('/{farm}', [FarmController::class, 'update'])->name('update');

            Route::get('/{farm}/desactivar', [FarmStatusController::class, 'confirmDeactivation'])->name('deactivate.confirm');
            Route::patch('/{farm}/desactivar', [FarmStatusController::class, 'deactivate'])->name('deactivate');
            Route::patch('/{farm}/activar', [FarmStatusController::class, 'activate'])->name('activate');

            Route::post('/{farm}/asignaciones', [FarmAssignmentController::class, 'store'])->name('assignments.store');
            Route::delete('/{farm}/asignaciones/{user}', [FarmAssignmentController::class, 'destroy'])->name('assignments.destroy');
        });

        Route::get('/', [FarmController::class, 'index'])->middleware('can:viewAny,'.Farm::class)->name('index');
        Route::get('/{farm}', [FarmController::class, 'show'])->middleware('can:view,farm')->name('show');
    });

    Route::prefix('estanques')->name('ponds.')->group(function () {
        Route::middleware('can:manage-ponds')->group(function () {
            Route::get('/crear', [PondController::class, 'create'])->name('create');
            Route::post('/', [PondController::class, 'store'])->name('store');
            Route::get('/{pond}/editar', [PondController::class, 'edit'])->name('edit');
            Route::put('/{pond}', [PondController::class, 'update'])->name('update');

            Route::get('/{pond}/desactivar', [PondStatusController::class, 'confirmDeactivation'])->name('deactivate.confirm');
            Route::patch('/{pond}/desactivar', [PondStatusController::class, 'deactivate'])->name('deactivate');
            Route::patch('/{pond}/activar', [PondStatusController::class, 'activate'])->name('activate');
        });

        Route::get('/', [PondController::class, 'index'])->middleware('can:viewAny,'.Pond::class)->name('index');
        Route::get('/{pond}', [PondController::class, 'show'])->middleware('can:view,pond')->name('show');
    });

    Route::prefix('lotes')->name('batches.')->group(function () {
        Route::middleware('can:manage-batches')->group(function () {
            Route::get('/crear', [BatchController::class, 'create'])->name('create');
            Route::post('/', [BatchController::class, 'store'])->name('store');
            Route::get('/{batch}/editar', [BatchController::class, 'edit'])->name('edit');
            Route::put('/{batch}', [BatchController::class, 'update'])->name('update');

            Route::get('/{batch}/cerrar', [BatchClosureController::class, 'confirmClose'])->name('close.confirm');
            Route::patch('/{batch}/cerrar', [BatchClosureController::class, 'close'])->name('close');
            Route::get('/{batch}/cancelar', [BatchClosureController::class, 'confirmCancel'])->name('cancel.confirm');
            Route::patch('/{batch}/cancelar', [BatchClosureController::class, 'cancel'])->name('cancel');
        });

        Route::get('/', [BatchController::class, 'index'])->middleware('can:viewAny,'.Batch::class)->name('index');
        Route::get('/{batch}', [BatchController::class, 'show'])->middleware('can:view,batch')->name('show');
    });

    Route::prefix('catalogos')->name('catalogs.')->group(function () {
        Route::redirect('/', '/catalogos/'.Catalog::Species->value)->name('home');

        Route::middleware('can:manage-catalogs')->group(function () {
            Route::get('/{catalog}/crear', [CatalogController::class, 'create'])->name('create');
            Route::post('/{catalog}', [CatalogController::class, 'store'])->name('store');
            Route::get('/{catalog}/{item}/editar', [CatalogController::class, 'edit'])->name('edit');
            Route::put('/{catalog}/{item}', [CatalogController::class, 'update'])->name('update');

            Route::get('/{catalog}/{item}/desactivar', [CatalogStatusController::class, 'confirmDeactivation'])->name('deactivate.confirm');
            Route::patch('/{catalog}/{item}/desactivar', [CatalogStatusController::class, 'deactivate'])->name('deactivate');
            Route::patch('/{catalog}/{item}/activar', [CatalogStatusController::class, 'activate'])->name('activate');
        });

        Route::get('/{catalog}', [CatalogController::class, 'index'])->name('index');
    });
});

require __DIR__.'/auth.php';
