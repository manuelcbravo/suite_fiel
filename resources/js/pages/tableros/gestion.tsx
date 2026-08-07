import { Head } from '@inertiajs/react';
import { columnChart, groupedBar, pieDonut } from '@/components/charts/builders';
import { GaugeSemi } from '@/components/charts/gauge-semi';
import { HChart } from '@/components/charts/hchart';
import { Panel, StatCard } from '@/components/charts/panel';
import { Encabezado, Seccion } from '@/components/pagina';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Par = [string, number];
type Kpis = {
    capturadas: number;
    turnadas: number;
    para_resolver: number;
    resueltas: number;
    total: number;
    compromisos: number;
};
type Gestor = {
    gestores: number;
    total: number;
    resueltas: number;
    para_resolver: number;
    turnadas: number;
    capturadas: number;
};
type Matriz = {
    asociacion: { personal: number; comunitaria: number };
    ciudadano: { personal: number; comunitaria: number };
};
type FilaLoc = {
    localidad: string;
    total: number;
    resueltas: number;
    para_resolver: number;
    turnadas: number;
    capturadas: number;
    inversion: number;
};

const nf = new Intl.NumberFormat('es-MX');
const mf = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

export default function Gestion({
    kpis,
    asociaciones,
    ciudadanos,
    cumplimiento,
    porResolver,
    atendidasPorDependencia,
    porConcepto,
    porTipoBeneficiario,
    topLocalidadCantidad,
    tablaLocalidades,
}: {
    kpis: Kpis;
    asociaciones: Gestor;
    ciudadanos: Gestor;
    cumplimiento: number;
    porResolver: Par[];
    atendidasPorDependencia: Par[];
    porConcepto: Par[];
    porTipoBeneficiario: Matriz;
    topLocalidadCantidad: Par[];
    tablaLocalidades: FilaLoc[];
}) {
    return (
        <>
            <Head title="Tablero de gestión" />
            <div className="space-y-4 p-4">
                <Encabezado
                    titulo="Tablero de gestión"
                    resumen={
                        <>
                            {nf.format(kpis.total)} solicitudes ·{' '}
                            {cumplimiento.toFixed(0)}% de cumplimiento
                        </>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <StatCard
                        label="Capturadas"
                        value={kpis.capturadas}
                        color="orange"
                    />
                    <StatCard
                        label="Turnadas"
                        value={kpis.turnadas}
                        color="yellow"
                    />
                    <StatCard
                        label="Para resolver"
                        value={kpis.para_resolver}
                        color="blue"
                    />
                    <StatCard
                        label="Resueltas"
                        value={kpis.resueltas}
                        color="green"
                    />
                    <StatCard
                        label="Total de solicitudes"
                        value={kpis.total}
                        color="pink"
                    />
                    <StatCard
                        label="Compromisos próximos"
                        value={kpis.compromisos}
                        color="purple"
                    />
                </div>

                <Seccion>Carga por dependencia</Seccion>
                {/* Dos columnas y no tres: a un tercio de ancho las donas
                    quedaban del tamaño de una moneda y las etiquetas encimadas. */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Cumplimiento"
                        nota="Resueltas sobre el total"
                        className="flex flex-col"
                    >
                        <div className="flex flex-1 flex-col items-center justify-center gap-3">
                            <GaugeSemi value={cumplimiento} />
                            <p className="text-center text-sm text-muted-foreground">
                                {nf.format(kpis.resueltas)} de{' '}
                                {nf.format(kpis.total)} solicitudes resueltas
                            </p>
                        </div>
                    </Panel>
                    <Panel
                        title="Solicitudes por resolver"
                        nota="Pendientes por área"
                    >
                        <HChart options={pieDonut(porResolver)} />
                    </Panel>
                </div>

                <Panel title="Solicitudes atendidas" nota="Resueltas por área">
                    <HChart options={pieDonut(atendidasPorDependencia)} />
                </Panel>

                <Panel
                    title="Solicitudes atendidas por concepto"
                    nota="Qué se pide con más frecuencia"
                >
                    <HChart options={columnChart(porConcepto)} />
                </Panel>

                <Seccion>Quién gestiona</Seccion>
                <p className="text-sm font-medium">Asociaciones gestoras</p>
                <BarraGestor
                    g={asociaciones}
                    etiquetaGestores="Asociaciones gestoras"
                />
                <p className="pt-2 text-sm font-medium">Ciudadanos gestores</p>
                <BarraGestor
                    g={ciudadanos}
                    etiquetaGestores="Ciudadanos gestores"
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title="Solicitudes atendidas por tipo de beneficiario"
                        nota="Personal contra comunitaria"
                    >
                        <HChart
                            options={groupedBar(
                                ['Asociación', 'Ciudadano'],
                                [
                                    porTipoBeneficiario.asociacion.personal,
                                    porTipoBeneficiario.ciudadano.personal,
                                ],
                                [
                                    porTipoBeneficiario.asociacion.comunitaria,
                                    porTipoBeneficiario.ciudadano.comunitaria,
                                ],
                            )}
                        />
                    </Panel>
                    <Panel
                        title="Top 10 localidad por cantidad"
                        nota="Solicitudes recibidas"
                    >
                        <HChart options={columnChart(topLocalidadCantidad)} />
                    </Panel>
                </div>

                <Seccion>Desglose por localidad</Seccion>
                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Localidad</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Resueltas
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Para resolver
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Turnadas
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Capturadas
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Inversión
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {tablaLocalidades.map((f) => (
                                    <TableRow key={f.localidad}>
                                        <TableCell>{f.localidad}</TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {nf.format(f.total)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {nf.format(f.resueltas)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {nf.format(f.para_resolver)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {nf.format(f.turnadas)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {nf.format(f.capturadas)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {mf.format(f.inversion)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </>
    );
}

function BarraGestor({
    g,
    etiquetaGestores,
}: {
    g: Gestor;
    etiquetaGestores: string;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <StatCard
                label={etiquetaGestores}
                value={g.gestores}
                color="purple"
            />
            <StatCard
                label="Total de solicitudes"
                value={g.total}
                color="pink"
            />
            <StatCard
                label="Solicitudes resueltas"
                value={g.resueltas}
                color="green"
            />
            <StatCard
                label="Solicitudes a resolver"
                value={g.para_resolver}
                color="blue"
            />
            <StatCard
                label="Solicitudes turnadas"
                value={g.turnadas}
                color="yellow"
            />
            <StatCard
                label="Solicitudes capturadas"
                value={g.capturadas}
                color="orange"
            />
        </div>
    );
}

Gestion.layout = {
    breadcrumbs: [
        { title: 'Tableros', href: '/tableros/gestion' },
        { title: 'Gestión', href: '/tableros/gestion' },
    ],
};
