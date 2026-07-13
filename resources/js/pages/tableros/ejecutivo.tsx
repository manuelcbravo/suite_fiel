import { Head } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import { columnChart, pieDonut } from '@/components/charts/builders';
import { GaugeSemi } from '@/components/charts/gauge-semi';
import { HChart } from '@/components/charts/hchart';
import { Panel } from '@/components/charts/panel';

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
                <h1 className="text-lg font-semibold">Agenda</h1>
                <div className="grid gap-4 lg:grid-cols-2">
                    <ListaAgenda titulo="Actividades de hoy" eventos={agendaHoy} />
                    <ListaAgenda
                        titulo="Actividades de mañana"
                        eventos={agendaManana}
                    />
                </div>

                <div className="flex items-baseline justify-between">
                    <h1 className="text-lg font-semibold">
                        Gestión / Solicitudes
                    </h1>
                    <span className="text-sm text-muted-foreground">
                        Total: {nf.format(total)} solicitudes
                    </span>
                </div>
                <div className="grid gap-4 lg:grid-cols-3">
                    <Panel title="Estatus">
                        <HChart options={pieDonut(estatus)} />
                    </Panel>
                    <Panel title="Turnadas / Por resolver">
                        <HChart options={columnChart(porDependencia)} />
                    </Panel>
                    <Panel title="Cumplimiento">
                        <GaugeSemi value={cumplimiento} />
                    </Panel>
                </div>
            </div>
        </>
    );
}

function ListaAgenda({
    titulo,
    eventos,
}: {
    titulo: string;
    eventos: Evento[];
}) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
            <h2 className="mb-2 text-sm font-semibold">{titulo}</h2>
            <ul className="divide-y">
                {eventos.length === 0 && (
                    <li className="py-3 text-sm text-muted-foreground">
                        Sin actividades.
                    </li>
                )}
                {eventos.map((ev) => (
                    <li
                        key={ev.id}
                        className="flex items-center gap-3 py-2 text-sm"
                    >
                        <span className="flex items-center gap-1 text-xs tabular-nums text-muted-foreground">
                            <CalendarClock className="size-3" />
                            {ev.hora ?? '--:--'}
                        </span>
                        <span className="flex-1 truncate">{ev.titulo}</span>
                        {ev.lugar && (
                            <span className="truncate text-xs text-muted-foreground">
                                {ev.lugar}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

Ejecutivo.layout = {
    breadcrumbs: [
        { title: 'Tableros', href: '/tableros/ejecutivo' },
        { title: 'Ejecutivo', href: '/tableros/ejecutivo' },
    ],
};
