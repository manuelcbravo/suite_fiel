<?php

namespace Database\Seeders;

use App\Enums\Permiso;
use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Crea el catálogo de permisos y los roles de la plataforma.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permiso::cases() as $permiso) {
            Permission::findOrCreate($permiso->value);
        }

        // El DatabaseSeeder corre con WithoutModelEvents, que silencia el
        // evento "saved" con el que spatie invalida su caché de permisos;
        // sin esta limpieza, syncPermissions no encuentra los recién creados.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Rol::cases() as $rol) {
            Role::findOrCreate($rol->value)->syncPermissions(
                array_map(fn (Permiso $permiso): string => $permiso->value, $rol->permisos()),
            );
        }
    }
}
