import { Head } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { ColumnasField, useColumnas } from '@/components/columnas-field';
import type { ColumnaOpcion } from '@/components/columnas-field';
import { MultiCheckField } from '@/components/multi-check-field';
import { ReporteShell } from '@/components/reporte-shell';
import type { Columna, Paginacion } from '@/components/reporte-shell';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Opcion = { id: number; nombre: string };

type Filtros = {
    desde: string | null;
    hasta: string | null;
    origenes: number[];
    delitos: number[];
    niveles: number[];
    sectores: number[];
    etapas: number[];
    localidad_id: number | null;
    columnas: string[];
};

type Opciones = {
    origenes: Opcion[];
    delitos: Opcion[];
    niveles: Opcion[];
    sectores: Opcion[];
    estados: Opcion[];
    etapas: Opcion[];
    columnas: ColumnaOpcion[];
};

export default function ReporteSeguridad({
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
    const [origenes, setOrigenes] = useState<number[]>(filtros.origenes ?? []);
    const [delitos, setDelitos] = useState<number[]>(filtros.delitos ?? []);
    const [niveles, setNiveles] = useState<number[]>(filtros.niveles ?? []);
    const [sectores, setSectores] = useState<number[]>(filtros.sectores ?? []);
    const [etapas, setEtapas] = useState<number[]>(filtros.etapas ?? []);
    const [ubic, setUbic] = useState({
        estado_id: null as number | null,
        municipio_id: null as number | null,
        localidad_id: filtros.localidad_id,
    });
    // Qué columnas se llevan al reporte, como las casillas del legacy.
    const [columnas, setColumnas] = useColumnas(filtros.columnas);

    const params = {
        desde,
        hasta,
        origenes,
        delitos,
        niveles,
        sectores,
        etapas,
        localidad_id: ubic.localidad_id,
        columnas,
    };

    // Los encabezados salen de lo que el servidor ya aplicó, no de las fichas
    // marcadas: si no, la tabla cambiaría antes de traer los datos.
    const columnasTabla: Columna[] = opciones.columnas
        .filter((c) => filtros.columnas.includes(c.clave))
        .map((c) => ({ key: c.clave, header: c.label }));

    return (
        <>
            <Head title="Reporte de seguridad" />
            <ReporteShell
                title="Reporte de seguridad ciudadana"
                descripcion="Qué se denunció, dónde y cómo se atendió."
                sustantivo="denuncias"
                icon={ShieldAlert}
                pageUrl="/reportes/seguridad"
                exportBase="/reportes/seguridad.xlsx"
                params={params}
                onLimpiar={() => {
                    setDesde('');
                    setHasta('');
                    setOrigenes([]);
                    setDelitos([]);
                    setNiveles([]);
                    setSectores([]);
                    setEtapas([]);
                    setUbic({
                        estado_id: null,
                        municipio_id: null,
                        localidad_id: null,
                    });
                    setColumnas([]);
                }}
                columnasField={
                    <ColumnasField
                        opciones={opciones.columnas}
                        value={columnas}
                        onChange={setColumnas}
                    />
                }
                totalColumnas={opciones.columnas.length}
                columns={columnasTabla}
                filas={filas}
                paginacion={paginacion}
            >
                <Field>
                    <Label htmlFor="desde">Denuncia desde</Label>
                    <Input
                        id="desde"
                        type="date"
                        value={desde}
                        onChange={(e) => setDesde(e.target.value)}
                    />
                </Field>
                <Field>
                    <Label htmlFor="hasta">Denuncia hasta</Label>
                    <Input
                        id="hasta"
                        type="date"
                        value={hasta}
                        onChange={(e) => setHasta(e.target.value)}
                    />
                </Field>

                <MultiCheckField
                    label="Etapa"
                    options={opciones.etapas}
                    value={etapas}
                    onChange={setEtapas}
                />
                <MultiCheckField
                    label="Origen de denuncia"
                    options={opciones.origenes}
                    value={origenes}
                    onChange={setOrigenes}
                />
                <MultiCheckField
                    label="Presunto delito"
                    options={opciones.delitos}
                    value={delitos}
                    onChange={setDelitos}
                />
                <MultiCheckField
                    label="Nivel de violencia"
                    options={opciones.niveles}
                    value={niveles}
                    onChange={setNiveles}
                />
                <MultiCheckField
                    label="Sector"
                    options={opciones.sectores}
                    value={sectores}
                    onChange={setSectores}
                />

                <div className="sm:col-span-2 lg:col-span-3">
                    <p className="mb-1.5 text-sm font-medium">
                        Localidad de la incidencia
                    </p>
                    <UbicacionSelects
                        estados={opciones.estados}
                        value={ubic}
                        onChange={(patch) => setUbic({ ...ubic, ...patch })}
                    />
                </div>
            </ReporteShell>
        </>
    );
}

ReporteSeguridad.layout = {
    breadcrumbs: [
        { title: 'Reportes', href: '/reportes/seguridad' },
        { title: 'Seguridad', href: '/reportes/seguridad' },
    ],
};
