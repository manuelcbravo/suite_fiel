import { Head, router } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { useState } from 'react';
import { ColumnasField, useColumnas } from '@/components/columnas-field';
import type { ColumnaOpcion } from '@/components/columnas-field';
import { MultiCheckField } from '@/components/multi-check-field';
import { ReporteShell } from '@/components/reporte-shell';
import type { Columna, Paginacion } from '@/components/reporte-shell';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Opcion = { id: number; nombre: string };
type Filtros = {
    desde: string | null;
    hasta: string | null;
    estatus: number[];
    concepto_id: number | null;
    procedencia_id: number | null;
    control_administrativo: boolean | null;
    localidad_resp_id: number | null;
    monto_min: number | null;
    monto_max: number | null;
    vista: string;
    columnas: string[];
};
type Vista = { clave: string; label: string };

const TIPO_SOL: { id: number; nombre: string }[] = [
    { id: 0, nombre: 'Ciudadana' },
    { id: 1, nombre: 'Control administrativo' },
];
type Opciones = {
    estatus: Opcion[];
    conceptos: Opcion[];
    procedencias: Opcion[];
    estados: Opcion[];
    vistas: Vista[];
    columnas: ColumnaOpcion[];
    eligeColumnas: boolean;
};

export default function ReporteGestion({
    filtros,
    opciones,
    filas,
    paginacion,
}: {
    filtros: Filtros;
    opciones: Opciones;
    filas: Record<string, string | number | null>[];
    paginacion: Paginacion;
}) {
    const [desde, setDesde] = useState(filtros.desde ?? '');
    const [hasta, setHasta] = useState(filtros.hasta ?? '');
    const [estatus, setEstatus] = useState<number[]>(filtros.estatus ?? []);
    const [concepto, setConcepto] = useState<number | null>(
        filtros.concepto_id,
    );
    const [procedencia, setProcedencia] = useState<number | null>(
        filtros.procedencia_id,
    );
    const [tipoSol, setTipoSol] = useState<number | null>(
        filtros.control_administrativo === null
            ? null
            : filtros.control_administrativo
              ? 1
              : 0,
    );
    const [ubic, setUbic] = useState({
        estado_id: null as number | null,
        municipio_id: null as number | null,
        localidad_id: filtros.localidad_resp_id,
    });
    const [montoMin, setMontoMin] = useState(
        filtros.monto_min !== null ? String(filtros.monto_min) : '',
    );
    const [montoMax, setMontoMax] = useState(
        filtros.monto_max !== null ? String(filtros.monto_max) : '',
    );
    const [columnas, setColumnas] = useColumnas(filtros.columnas);

    // Cambiar de pestaña recarga: cada vista tiene sus propias columnas.
    const cambiarVista = (vista: string) =>
        router.get(
            '/reportes/gestion',
            { ...params, vista, columnas: [] },
            { preserveState: true, preserveScroll: true },
        );

    const params = {
        desde,
        hasta,
        estatus,
        concepto_id: concepto,
        procedencia_id: procedencia,
        control_administrativo: tipoSol,
        localidad_resp_id: ubic.localidad_id,
        monto_min: montoMin,
        monto_max: montoMax,
        vista: filtros.vista,
        columnas,
    };

    // Los encabezados salen de lo que el servidor ya aplicó, no de las fichas
    // marcadas: si no, la tabla cambiaría antes de traer los datos.
    const columnasTabla: Columna[] = opciones.columnas
        .filter((c) => filtros.columnas.includes(c.clave))
        .map((c) => ({ key: c.clave, header: c.label }));

    return (
        <>
            <Head title="Reporte de gestión" />
            <ReporteShell
                title="Reporte de gestión"
                descripcion="Qué pidió la gente y en qué acabó."
                // En General cada renglón es una localidad, no una solicitud.
                sustantivo={
                    filtros.vista === 'general' ? 'localidades' : 'solicitudes'
                }
                icon={ClipboardList}
                pageUrl="/reportes/gestion"
                exportBase="/reportes/gestion.xlsx"
                params={params}
                onLimpiar={() => {
                    setDesde('');
                    setHasta('');
                    setEstatus([]);
                    setConcepto(null);
                    setProcedencia(null);
                    setTipoSol(null);
                    setUbic({
                        estado_id: null,
                        municipio_id: null,
                        localidad_id: null,
                    });
                    setMontoMin('');
                    setMontoMax('');
                    setColumnas([]);
                }}
                pestanas={opciones.vistas.map((v) => ({
                    ...v,
                    activa: v.clave === filtros.vista,
                }))}
                onPestana={cambiarVista}
                columnasField={
                    opciones.eligeColumnas ? (
                        <ColumnasField
                            opciones={opciones.columnas}
                            value={columnas}
                            onChange={setColumnas}
                        />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Esta vista sale con sus columnas fijas, como en la
                            plataforma anterior.
                        </p>
                    )
                }
                totalColumnas={opciones.columnas.length}
                columns={columnasTabla}
                filas={filas}
                paginacion={paginacion}
            >
                <Field>
                    <Label htmlFor="desde">Recepción desde</Label>
                    <Input
                        id="desde"
                        type="date"
                        value={desde}
                        onChange={(e) => setDesde(e.target.value)}
                    />
                </Field>
                <Field>
                    <Label htmlFor="hasta">Recepción hasta</Label>
                    <Input
                        id="hasta"
                        type="date"
                        value={hasta}
                        onChange={(e) => setHasta(e.target.value)}
                    />
                </Field>
                <SelectField
                    label="Concepto"
                    value={concepto}
                    options={opciones.conceptos}
                    onChange={setConcepto}
                />
                <SelectField
                    label="Procedencia"
                    value={procedencia}
                    options={opciones.procedencias}
                    onChange={setProcedencia}
                />
                <SelectField
                    label="Tipo de solicitud"
                    value={tipoSol}
                    options={TIPO_SOL}
                    onChange={setTipoSol}
                />
                <Field>
                    <Label htmlFor="monto-min">Monto mínimo</Label>
                    <Input
                        id="monto-min"
                        type="number"
                        value={montoMin}
                        onChange={(e) => setMontoMin(e.target.value)}
                    />
                </Field>
                <Field>
                    <Label htmlFor="monto-max">Monto máximo</Label>
                    <Input
                        id="monto-max"
                        type="number"
                        value={montoMax}
                        onChange={(e) => setMontoMax(e.target.value)}
                    />
                </Field>
                <div className="sm:col-span-3 lg:col-span-4">
                    <UbicacionSelects
                        estados={opciones.estados}
                        value={ubic}
                        onChange={(patch) => setUbic({ ...ubic, ...patch })}
                    />
                </div>
                <div className="sm:col-span-3 lg:col-span-4">
                    <MultiCheckField
                        label="Estatus"
                        options={opciones.estatus}
                        value={estatus}
                        onChange={setEstatus}
                    />
                </div>
            </ReporteShell>
        </>
    );
}

ReporteGestion.layout = {
    breadcrumbs: [
        { title: 'Reportes', href: '/reportes/gestion' },
        { title: 'Gestión', href: '/reportes/gestion' },
    ],
};
