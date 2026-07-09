<?php

use App\Http\Controllers\Config\CatalogoController;
use App\Http\Controllers\Config\RoleController;
use App\Http\Controllers\Config\UserController;
use App\Http\Controllers\Directorio\BeneficiarioController;
use App\Http\Controllers\Directorio\OrganizacionController;
use App\Http\Controllers\Directorio\ProveedorController;
use App\Http\Controllers\Directorio\UbicacionController;
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

    // Catálogos (Módulo 0): pantalla única con pestañas.
    Route::prefix('config')->middleware('can:catalogos.gestionar')->group(function () {
        Route::get('catalogos', [CatalogoController::class, 'index'])->name('config.catalogos.index');
        Route::post('catalogos/{catalogo}', [CatalogoController::class, 'store'])->name('config.catalogos.store');
        Route::delete('catalogos/{catalogo}/{id}', [CatalogoController::class, 'destroy'])->name('config.catalogos.destroy');
    });

    // Directorio (Módulo 2): beneficiarios, organizaciones, proveedores.
    Route::prefix('directorio')->middleware('can:directorio.gestionar')->group(function () {
        // Lookups geográficos dependientes (reutilizables).
        Route::get('ubicaciones/municipios', [UbicacionController::class, 'municipios'])->name('directorio.ubicaciones.municipios');
        Route::get('ubicaciones/localidades', [UbicacionController::class, 'localidades'])->name('directorio.ubicaciones.localidades');

        Route::get('beneficiarios/buscar', [BeneficiarioController::class, 'buscar'])->name('directorio.beneficiarios.buscar');
        Route::get('beneficiarios', [BeneficiarioController::class, 'index'])->name('directorio.beneficiarios.index');
        Route::post('beneficiarios', [BeneficiarioController::class, 'store'])->name('directorio.beneficiarios.store');
        Route::delete('beneficiarios/{beneficiario}', [BeneficiarioController::class, 'destroy'])->name('directorio.beneficiarios.destroy');

        Route::get('organizaciones', [OrganizacionController::class, 'index'])->name('directorio.organizaciones.index');
        Route::post('organizaciones', [OrganizacionController::class, 'store'])->name('directorio.organizaciones.store');
        Route::delete('organizaciones/{organizacion}', [OrganizacionController::class, 'destroy'])->name('directorio.organizaciones.destroy');

        Route::get('proveedores', [ProveedorController::class, 'index'])->name('directorio.proveedores.index');
        Route::post('proveedores', [ProveedorController::class, 'store'])->name('directorio.proveedores.store');
        Route::delete('proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('directorio.proveedores.destroy');
    });
});

require __DIR__.'/settings.php';
