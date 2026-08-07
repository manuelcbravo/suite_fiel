import { Head } from '@inertiajs/react';
import { columnChart, pieDonut } from '@/components/charts/builders';
import { GaugeSemi } from '@/components/charts/gauge-semi';
import { HChart } from '@/components/charts/hchart';
import { Panel } from '@/components/charts/panel';
import { Encabezado, Seccion } from '@/components/pagina';
import { TONO } from '../obra/etapas';
import type { Punto } from './mapa-incidencias';
import { MapaIncidencias } from './mapa-incidencias';

type Par = [string, number];
type Resumen = {
    total: number;
    ficha: number;
    expediente: number;
    autorizacion: number;
    adjudicacion: number;
    ejecucion: number;
    conclusion: number;
    entrega: number;
};

const nf = new Intl.NumberFormat('es-MX');

/** Los siete acuses de etapa, en el orden del expediente. */
const ETAPAS: { clave: keyof Resumen; label: string }[] = [
    { clave: 'ficha', label: 'Ficha técnica' },
    { clave: 'expediente', label: 'Expediente técnico' },
    { clave: 'autorizacion', label: 'Con oficio de autorización' },
    { clave: 'adjudicacion', label: 'Adjudicadas' },
    { clave: 'ejecucion', label: 'Ejecutadas' },
    { clave: 'conclusion', label: 'Terminadas' },
    { clave: 'entrega', label: 'Con acta de entrega-recepción' },
];

export default function TableroObra({
    resumen,
    cumplimiento,
    porEtapa,
    topLocalidad,
    obras,
}: {
    resumen: Resumen;
    cumplimiento: number;
    porEtapa: Par[];
    topLocalidad: Par[];
    obras: Punto[];
}) {
    return (
        <>
            <Head title="Tablero de obra pública" />
            <div className="space-y-4 p-4">
                <Encabezado
                    titulo="Obra pública"
                    resumen={
                        <>
                            {nf.format(resumen.total)} obras ·{' '}
                            {cumplimiento.toFixed(0)}% entregadas
                        </>
                    }
                />

                {/* El mismo tablero de acuses del legacy, en el mismo orden. */}
                <Seccion>Hasta dónde llegó cada obra</Seccion>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    {ETAPAS.map(({ clave, label }, i) => (
                        <div
                            key={clave}
                            className="flex flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card"
                        >
                            <div className="flex-1 p-4">
                                <p className="text-2xl font-semibold tracking-tight tabular-nums">
                                    {nf.format(resumen[clave])}
                                </p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {label}
                                </p>
                            </div>
                            {/* Al pie, qué tanto del padrón es esa cifra: con
                                las siete tarjetas del mismo tamaño no se veía
                                que la ejecución va muy por detrás del resto. */}
                            <div
                                className="h-1 bg-muted"
                                title={`${resumen[clave]} de ${resumen.total} obras`}
                            >
                                <div
                                    className={`h-full w-[var(--ancho)] animate-[crecer-barra_700ms_ease-out_both] motion-reduce:animate-none ${TONO[clave]}`}
                                    style={
                                        {
                                            '--ancho': `${resumen.total > 0 ? (resumen[clave] * 100) / resumen.total : 0}%`,
                                            animationDelay: `${i * 40}ms`,
                                        } as React.CSSProperties
                                    }
                                />
                            </div>
                        </div>
                    ))}
                </div>

                <Seccion>Avance e inversión</Seccion>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Obras entregadas"
                        nota="Con acta de entrega-recepción, sobre el total"
                        className="flex flex-col"
                    >
                        <div className="flex flex-1 flex-col items-center justify-center gap-3">
                            <GaugeSemi value={cumplimiento} />
                            <p className="text-center text-sm text-muted-foreground">
                                {nf.format(resumen.entrega)} de{' '}
                                {nf.format(resumen.total)} obras entregadas
                            </p>
                        </div>
                    </Panel>
                    <Panel
                        title="En qué punto está el padrón"
                        nota="Cada obra cuenta una sola vez"
                    >
                        <HChart options={pieDonut(porEtapa)} />
                    </Panel>
                </div>

                <Panel
                    title="Localidades con mayor inversión"
                    nota="Las 10 primeras, por monto de la obra"
                >
                    <HChart options={columnChart(topLocalidad, true)} />
                </Panel>

                <Panel
                    title="Mapa de obras"
                    nota={`${nf.format(obras.length)} georreferenciadas`}
                >
                    <MapaIncidencias
                        puntos={obras}
                        etapas={['Sin adjudicar', 'En ejecución', 'Entregada']}
                        sustantivo="obra"
                        vacio="Todavía no hay obras ubicadas. La coordenada se marca en el mapa de la ficha técnica."
                    />
                </Panel>
            </div>
        </>
    );
}

TableroObra.layout = {
    breadcrumbs: [
        { title: 'Tableros', href: '/tableros/obra' },
        { title: 'Obra pública', href: '/tableros/obra' },
    ],
};
