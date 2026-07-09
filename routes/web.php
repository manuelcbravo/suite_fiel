<?php

use App\Http\Controllers\Config\RoleController;
use App\Http\Controllers\Config\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Administración de usuarios y roles (protegida por permiso).
    Route::prefix('config')->middleware('can:usuarios.gestionar')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('config.users.index');
        Route::post('users', [UserController::class, 'store'])->name('config.users.store');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('config.users.destroy');
        Route::get('roles', [RoleController::class, 'index'])->name('config.roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('config.roles.store');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('config.roles.destroy');
    });
});

require __DIR__.'/settings.php';
