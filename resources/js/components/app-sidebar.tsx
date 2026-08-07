import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronRight,
    ClipboardList,
    Contact,
    FileSpreadsheet,
    GraduationCap,
    HardHat,
    LayoutGrid,
    MailPlus,
    Settings,
    ShieldAlert,
    StickyNote,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { IsCurrentUrlFn } from '@/hooks/use-current-url';
import { usePermisos } from '@/hooks/use-permisos';
import { dashboard } from '@/routes';
import { index as agendaIndex } from '@/routes/agenda';
import { index as capacitacionIndex } from '@/routes/capacitacion';
import { index as configCatalogos } from '@/routes/config/catalogos';
import { index as configRoles } from '@/routes/config/roles';
import { index as configUsers } from '@/routes/config/users';
import { index as directorio } from '@/routes/directorio';
import { index as gestionSolicitudes } from '@/routes/gestion/solicitudes';
import { index as invitacionesIndex } from '@/routes/invitaciones';
import { index as notasIndex } from '@/routes/notas';
import { index as obraIndex } from '@/routes/obra';
import {
    agenda as reporteAgenda,
    directorio as reporteDirectorio,
    gestion as reporteGestion,
    obra as reporteObra,
    seguridad as reporteSeguridad,
} from '@/routes/reportes';
import { index as seguridadDenuncias } from '@/routes/seguridad/denuncias';
import { index as seguridadDetenidos } from '@/routes/seguridad/detenidos';
import {
    ejecutivo as tableroEjecutivo,
    financiero as tableroFinanciero,
    gestion as tableroGestion,
    obra as tableroObra,
    seguridad as tableroSeguridad,
} from '@/routes/tableros';

type Href = NonNullable<InertiaLinkProps['href']>;
type Enlace = { title: string; href: Href };
type Grupo = {
    title: string;
    icon: LucideIcon;
    visible: boolean;
    items: Enlace[];
};

export function AppSidebar() {
    const { puede } = usePermisos();
    const { isCurrentUrl } = useCurrentUrl();

    const tablerosItems: Enlace[] = [
        ...(puede('tableros.ejecutivo')
            ? [{ title: 'Ejecutivo', href: tableroEjecutivo() }]
            : []),
        ...(puede('tableros.gestion')
            ? [{ title: 'Gestión', href: tableroGestion() }]
            : []),
        ...(puede('tableros.financiero')
            ? [{ title: 'Financiero', href: tableroFinanciero() }]
            : []),
        ...(puede('tableros.seguridad')
            ? [{ title: 'Seguridad', href: tableroSeguridad() }]
            : []),
        ...(puede('tableros.obra')
            ? [{ title: 'Obra pública', href: tableroObra() }]
            : []),
    ];

    const gestionItems: Enlace[] = puede('gestion.gestionar')
        ? [{ title: 'Solicitudes', href: gestionSolicitudes() }]
        : [];

    const seguridadItems: Enlace[] = puede('seguridad.gestionar')
        ? [
              { title: 'Denuncias', href: seguridadDenuncias() },
              { title: 'Detenidos', href: seguridadDetenidos() },
          ]
        : [];

    const reportesItems: Enlace[] = [
        ...(puede('reportes.agenda')
            ? [{ title: 'Agenda', href: reporteAgenda() }]
            : []),
        ...(puede('reportes.directorio')
            ? [{ title: 'Directorio', href: reporteDirectorio() }]
            : []),
        ...(puede('reportes.gestion')
            ? [{ title: 'Gestión', href: reporteGestion() }]
            : []),
        ...(puede('obra.gestionar')
            ? [{ title: 'Obra pública', href: reporteObra() }]
            : []),
        ...(puede('seguridad.gestionar')
            ? [{ title: 'Seguridad', href: reporteSeguridad() }]
            : []),
    ];

    const configuracionItems: Enlace[] = [
        ...(puede('usuarios.gestionar')
            ? [
                  { title: 'Usuarios', href: configUsers() },
                  { title: 'Roles y permisos', href: configRoles() },
              ]
            : []),
        ...(puede('catalogos.gestionar')
            ? [{ title: 'Catálogos', href: configCatalogos() }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {tablerosItems.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <GrupoNav
                                grupo={{
                                    title: 'Tableros',
                                    icon: LayoutGrid,
                                    visible: true,
                                    items: tablerosItems,
                                }}
                                isCurrentUrl={isCurrentUrl}
                            />
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {puede('agenda.gestionar') && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(agendaIndex())}
                                    tooltip={{ children: 'Agenda' }}
                                >
                                    <Link href={agendaIndex()} prefetch>
                                        <CalendarDays />
                                        <span>Agenda</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {/* El Directorio es una sola pantalla; las tres categorías son
                    pestañas dentro de ella, no entradas del menú. */}
                {puede('directorio.gestionar') && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(directorio())}
                                    tooltip={{ children: 'Directorio' }}
                                >
                                    <Link href={directorio()} prefetch>
                                        <Contact />
                                        <span>Directorio</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {gestionItems.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <GrupoNav
                                grupo={{
                                    title: 'Gestión',
                                    icon: ClipboardList,
                                    visible: true,
                                    items: gestionItems,
                                }}
                                isCurrentUrl={isCurrentUrl}
                            />
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {seguridadItems.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <GrupoNav
                                grupo={{
                                    title: 'Seguridad',
                                    icon: ShieldAlert,
                                    visible: true,
                                    items: seguridadItems,
                                }}
                                isCurrentUrl={isCurrentUrl}
                            />
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {puede('obra.gestionar') && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(obraIndex())}
                                    tooltip={{ children: 'Obra pública' }}
                                >
                                    <Link href={obraIndex()} prefetch>
                                        <HardHat />
                                        <span>Obra pública</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {puede('invitaciones.gestionar') && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(invitacionesIndex())}
                                    tooltip={{ children: 'Invitaciones' }}
                                >
                                    <Link href={invitacionesIndex()} prefetch>
                                        <MailPlus />
                                        <span>Invitaciones</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {puede('notas.gestionar') && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(notasIndex())}
                                    tooltip={{ children: 'Notas' }}
                                >
                                    <Link href={notasIndex()} prefetch>
                                        <StickyNote />
                                        <span>Notas</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {reportesItems.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <GrupoNav
                                grupo={{
                                    title: 'Reportes',
                                    icon: FileSpreadsheet,
                                    visible: true,
                                    items: reportesItems,
                                }}
                                isCurrentUrl={isCurrentUrl}
                            />
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {puede('capacitacion.ver') && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(capacitacionIndex())}
                                    tooltip={{ children: 'Capacitación' }}
                                >
                                    <Link href={capacitacionIndex()} prefetch>
                                        <GraduationCap />
                                        <span>Capacitación</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>
                {configuracionItems.length > 0 && (
                    <SidebarMenu>
                        <GrupoNav
                            grupo={{
                                title: 'Configuración',
                                icon: Settings,
                                visible: true,
                                items: configuracionItems,
                            }}
                            isCurrentUrl={isCurrentUrl}
                        />
                    </SidebarMenu>
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

function GrupoNav({
    grupo,
    isCurrentUrl,
}: {
    grupo: Grupo;
    isCurrentUrl: IsCurrentUrlFn;
}) {
    const abierto = grupo.items.some((item) => isCurrentUrl(item.href));

    return (
        <Collapsible defaultOpen={abierto} className="group/collapsible">
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton tooltip={{ children: grupo.title }}>
                        <grupo.icon />
                        <span>{grupo.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {grupo.items.map((item) => (
                            <SidebarMenuSubItem key={item.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentUrl(item.href)}
                                >
                                    <Link href={item.href} prefetch>
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
