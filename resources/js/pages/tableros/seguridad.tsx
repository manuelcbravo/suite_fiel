import { Head } from '@inertiajs/react';
import { columnChart, pieDonut } from '@/components/charts/builders';
import { HChart } from '@/components/charts/hchart';
import { Panel } from '@/components/charts/panel';
import { Encabezado } from '@/components/pagina';
import type { Punto } from './mapa-incidencias';
import { MapaIncidencias } from './mapa-incidencias';

type Par = [string, number];
type Resumen = {
    total: number;
    recibidas: number;
    enAtencion: number;
    concluidas: number;
    detenidos: number;
};

const nf = new Intl.NumberFormat('es-MX');

export default function TableroSeguridad({
    resumen,
    porEtapa,
    porDelito,
    incidencias,
}: {
    resumen: Resumen;
    porEtapa: Par[];
    porDelito: Par[];
    incidencias: Punto[];
}) {
    return (
        <>
            <Head title="Tablero de seguridad" />
            <div className="space-y-4 p-4">
                <Encabezado
                    titulo="Seguridad ciudadana"
                    resumen={
                        <>
                            {nf.format(resumen.total)} denuncias ·{' '}
                            {nf.format(resumen.detenidos)} detenidos
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <Cifra
                        titulo="En recepción"
                        valor={resumen.recibidas}
                        nota="Sin toma de conocimiento"
                    />
                    <Cifra
                        titulo="En atención"
                        valor={resumen.enAtencion}
                        nota="Con oficial asignado"
                    />
                    <Cifra
                        titulo="Concluidas"
                        valor={resumen.concluidas}
                        nota="Con término registrado"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Denuncias por etapa"
                        nota="Sobre el total registrado"
                    >
                        <HChart options={pieDonut(porEtapa)} />
                    </Panel>
                    <Panel
                        title="Presuntos delitos más frecuentes"
                        nota="Denuncias por clasificación"
                    >
                        <HChart options={columnChart(porDelito)} />
                    </Panel>
                </div>

                <Panel
                    title="Mapa de incidencias"
                    nota={`${nf.format(incidencias.length)} georreferenciadas`}
                >
                    <MapaIncidencias puntos={incidencias} />
                </Panel>
            </div>
        </>
    );
}

/** Cifra grande con su nota, para el resumen de arriba. */
function Cifra({
    titulo,
    valor,
    nota,
}: {
    titulo: string;
    valor: number;
    nota: string;
}) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
            <p className="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                {titulo}
            </p>
            <p className="mt-1 text-3xl font-semibold tracking-tight tabular-nums">
                {nf.format(valor)}
            </p>
            <p className="mt-0.5 text-xs text-muted-foreground">{nota}</p>
        </div>
    );
}

TableroSeguridad.layout = {
    breadcrumbs: [
        { title: 'Tableros', href: '/tableros/seguridad' },
        { title: 'Seguridad', href: '/tableros/seguridad' },
    ],
};
