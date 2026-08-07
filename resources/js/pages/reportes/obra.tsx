import { Head } from '@inertiajs/react';
import { HardHat } from 'lucide-react';
import { useState } from 'react';
import { ColumnasField, useColumnas } from '@/components/columnas-field';
import type { ColumnaOpcion } from '@/components/columnas-field';
import { ReporteShell } from '@/components/reporte-shell';
import type { Columna, Paginacion } from '@/components/reporte-shell';
import { SelectField } from '@/components/select-field';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Opcion = { id: number; nombre: string };
type Filtros = {
    ejercicio: string;
    modalidad: number | null;
    tipo_contratacion: number | null;
    columnas: string[];
};
type Opciones = {
    modalidades: Opcion[];
    contrataciones: Opcion[];
    columnas: ColumnaOpcion[];
};

export default function ReporteObra({
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
    const [ejercicio, setEjercicio] = useState(filtros.ejercicio);
    const [modalidad, setModalidad] = useState<number | null>(filtros.modalidad);
    const [contratacion, setContratacion] = useState<number | null>(
        filtros.tipo_contratacion,
    );
    const [columnas, setColumnas] = useColumnas(filtros.columnas);

    const params = {
        ejercicio,
        modalidad,
        tipo_contratacion: contratacion,
        columnas,
    };

    const columnasTabla: Columna[] = opciones.columnas
        .filter((c) => filtros.columnas.includes(c.clave))
        .map((c) => ({ key: c.clave, header: c.label }));

    return (
        <>
            <Head title="Reporte de obra pública" />
            <ReporteShell
                title="Reporte de obra pública"
                descripcion="En qué se invirtió, con quién se contrató y cómo va."
                sustantivo="obras"
                icon={HardHat}
                pageUrl="/reportes/obra"
                exportBase="/reportes/obra.xlsx"
                params={params}
                onLimpiar={() => {
                    setEjercicio('');
                    setModalidad(null);
                    setContratacion(null);
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
                    <Label htmlFor="ejercicio">Ejercicio fiscal</Label>
                    <Input
                        id="ejercicio"
                        value={ejercicio}
                        placeholder="2017"
                        onChange={(e) => setEjercicio(e.target.value)}
                    />
                </Field>
                <SelectField
                    label="Modalidad de ejecución"
                    value={modalidad}
                    options={opciones.modalidades}
                    onChange={setModalidad}
                />
                <SelectField
                    label="Tipo de contratación"
                    value={contratacion}
                    options={opciones.contrataciones}
                    onChange={setContratacion}
                />
            </ReporteShell>
        </>
    );
}

ReporteObra.layout = {
    breadcrumbs: [
        { title: 'Reportes', href: '/reportes/obra' },
        { title: 'Obra pública', href: '/reportes/obra' },
    ],
};
