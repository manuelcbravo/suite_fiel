import { Head } from '@inertiajs/react';
import { CalendarClock, MapPin } from 'lucide-react';
import { columnChart, pieDonut } from '@/components/charts/builders';
import { GaugeSemi } from '@/components/charts/gauge-semi';
import { HChart } from '@/components/charts/hchart';
import { Panel } from '@/components/charts/panel';
import { Encabezado, Seccion } from '@/components/pagina';

type Evento = {
    id: number;
    titulo: string;
    hora: string | null;
    lugar: string | null;
};
type Par = [string, number];

const nf = new Intl.NumberFormat('es-MX');

export default function Ejecutivo({
    agendaHoy,
    agendaManana,
    total,
    estatus,
    porDependencia,
    cumplimiento,
}: {
    agendaHoy: Evento[];
    agendaManana: Evento[];
    total: number;
    estatus: Par[];
    porDependencia: Par[];
    cumplimiento: number;
}) {
    return (
        <>
            <Head title="Tablero ejecutivo" />
            <div className="space-y-4 p-4">
                <Encabezado
                    titulo="Tablero ejecutivo"
                    resumen={
                        <>
                            {nf.format(total)} solicitudes ·{' '}
                            {cumplimiento.toFixed(0)}% de cumplimiento
                        </>
                    }
                />

                <Seccion>Agenda</Seccion>
                <div className="grid gap-4 lg:grid-cols-2">
                    <ListaAgenda
                        titulo="Actividades de hoy"
                        eventos={agendaHoy}
                        cuando="hoy"
                    />
                    <ListaAgenda
                        titulo="Actividades de mañana"
                        eventos={agendaManana}
                        cuando="mañana"
                    />
                </div>

                <Seccion>Gestión de solicitudes</Seccion>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Estatus" nota="Del total histórico">
                        <HChart options={pieDonut(estatus)} />
                    </Panel>
                    <Panel
                        title="Cumplimiento"
                        nota="Resueltas sobre el total"
                        className="flex flex-col"
                    >
                        <div className="flex flex-1 items-center justify-center">
                            <GaugeSemi value={cumplimiento} />
                        </div>
                    </Panel>
                </div>

                {/* A todo lo ancho: son doce dependencias con nombres largos y
                    en un tercio de pantalla las etiquetas se cortaban. */}
                <Panel
                    title="Turnadas / Por resolver"
                    nota="Pendientes en el escritorio de cada área"
                >
                    <HChart options={columnChart(porDependencia)} />
                </Panel>
            </div>
        </>
    );
}

function ListaAgenda({
    titulo,
    eventos,
    cuando,
}: {
    titulo: string;
    eventos: Evento[];
    cuando: string;
}) {
    return (
        <Panel
            title={titulo}
            nota={
                eventos.length > 0
                    ? `${eventos.length} ${eventos.length === 1 ? 'actividad' : 'actividades'}`
                    : undefined
            }
        >
            {eventos.length === 0 ? (
                <p className="py-4 text-sm text-muted-foreground">
                    No hay actividades programadas para {cuando}.
                </p>
            ) : (
                <ul className="divide-y">
                    {eventos.map((ev) => (
                        <li
                            key={ev.id}
                            className="flex items-baseline gap-3 py-2 text-sm first:pt-0"
                        >
                            <span className="flex shrink-0 items-center gap-1 text-xs tabular-nums text-muted-foreground">
                                <CalendarClock className="size-3" />
                                {ev.hora ?? '--:--'}
                            </span>
                            <span className="min-w-0 flex-1 break-words">
                                {ev.titulo}
                            </span>
                            {ev.lugar && (
                                <span className="flex max-w-[40%] shrink-0 items-center gap-1 truncate text-xs text-muted-foreground">
                                    <MapPin className="size-3 shrink-0" />
                                    {ev.lugar}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

Ejecutivo.layout = {
    breadcrumbs: [
        { title: 'Tableros', href: '/tableros/ejecutivo' },
        { title: 'Ejecutivo', href: '/tableros/ejecutivo' },
    ],
};
