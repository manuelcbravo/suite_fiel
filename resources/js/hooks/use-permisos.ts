import { usePage } from '@inertiajs/react';

/**
 * Permisos del usuario autenticado compartidos por HandleInertiaRequests.
 * El super admin recibe el catálogo completo desde el backend.
 */
export function usePermisos() {
    const { auth } = usePage().props;

    const puede = (permiso: string) => auth.permisos.includes(permiso);

    return { puede, permisos: auth.permisos };
}
