<?php

namespace App\Enums;

/**
 * Catálogo de permisos de la plataforma (formato modulo.accion).
 *
 * Base genérica: solo se incluye el permiso que controla la propia
 * administración de usuarios y roles. Agrega aquí los permisos de cada
 * módulo nuevo y el seeder los creará automáticamente.
 */
enum Permiso: string
{
    // Administración
    case UsuariosGestionar = 'usuarios.gestionar';

    public function label(): string
    {
        return match ($this) {
            self::UsuariosGestionar => 'Gestionar usuarios y roles',
        };
    }
}
