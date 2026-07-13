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

    // Catálogos (Módulo 0): estados, municipios, sectores, dependencias, ejes…
    case CatalogosGestionar = 'catalogos.gestionar';

    // Directorio (Módulo 2): beneficiarios, organizaciones, proveedores.
    case DirectorioGestionar = 'directorio.gestionar';

    // Gestión (Módulo 3): solicitudes y seguimientos.
    case GestionGestionar = 'gestion.gestionar';

    // Agenda (Módulo 4): calendario de eventos.
    case AgendaGestionar = 'agenda.gestionar';

    // Invitaciones (Módulo 6).
    case InvitacionesGestionar = 'invitaciones.gestionar';

    // Notas (Módulo 5).
    case NotasGestionar = 'notas.gestionar';

    // Reportes (Módulo 8): submódulos independientes con filtros.
    case ReportesVer = 'reportes.ver';
    case ReporteAgenda = 'reportes.agenda';
    case ReporteDirectorio = 'reportes.directorio';
    case ReporteGestion = 'reportes.gestion';

    // Capacitación (Módulo 7): ayuda y manuales.
    case CapacitacionVer = 'capacitacion.ver';

    // Tableros (Módulo 9): tableros independientes.
    case TableroEjecutivo = 'tableros.ejecutivo';
    case TableroGestion = 'tableros.gestion';
    case TableroFinanciero = 'tableros.financiero';

    // Seguridad ciudadana: denuncias y detenidos.
    case SeguridadGestionar = 'seguridad.gestionar';

    public function label(): string
    {
        return match ($this) {
            self::UsuariosGestionar => 'Gestionar usuarios y roles',
            self::CatalogosGestionar => 'Gestionar catálogos',
            self::DirectorioGestionar => 'Gestionar directorio',
            self::GestionGestionar => 'Gestionar solicitudes',
            self::AgendaGestionar => 'Gestionar agenda',
            self::InvitacionesGestionar => 'Gestionar invitaciones',
            self::NotasGestionar => 'Gestionar notas',
            self::ReportesVer => 'Ver reportes',
            self::ReporteAgenda => 'Reporte de agenda',
            self::ReporteDirectorio => 'Reporte de directorio',
            self::ReporteGestion => 'Reporte de gestión',
            self::CapacitacionVer => 'Ver capacitación',
            self::TableroEjecutivo => 'Ver tablero ejecutivo',
            self::TableroGestion => 'Ver tablero de gestión',
            self::TableroFinanciero => 'Ver tablero financiero',
            self::SeguridadGestionar => 'Gestionar seguridad',
        };
    }
}
