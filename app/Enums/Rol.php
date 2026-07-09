<?php

namespace App\Enums;

/**
 * Roles de la plataforma (spatie/permission, roles globales).
 *
 * El super admin NO es un rol: es el flag booleano users.es_super_admin
 * resuelto vía Gate::before (acceso total).
 *
 * Base genérica: define aquí los roles de tu aplicación y los permisos
 * que agrupa cada uno; el RoleSeeder los sincroniza.
 */
enum Rol: string
{
    case Administrador = 'administrador';

    /**
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Administrador => [
                Permiso::UsuariosGestionar,
                Permiso::CatalogosGestionar,
                Permiso::DirectorioGestionar,
            ],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
        };
    }
}
