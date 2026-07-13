import { Head } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { ReporteShell } from '@/components/reporte-shell';
import type { Columna, Paginacion } from '@/components/reporte-shell';
import { SelectField } from '@/components/select-field';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Opcion = { id: number; nombre: string };
type Filtros = {
    desde: string | null;
    hasta: string | null;
    tipo_evento_id: number | null;
    confirmado: boolean | null;
    discurso: boolean | null;
    privado: boolean | null;
};

const TRI: Opcion[] = [
    { id: 1, nombre: 'Sí' },
    { id: 0, nombre: 'No' },
];

const columnas: Columna[] = [
    { key: 'titulo', header: 'Título' },
    { key: 'tipo', header: 'Tipo' },
    { key: 'fecha', header: 'Fecha' },
    { key: 'hora', header: 'Hora' },
    { key: 'lugar', header: 'Lugar' },
    { key: 'contacto', header: 'Contacto' },
    { key: 'confirmado', header: 'Confirmado' },
];

const triNum = (v: boolean | null) => (v === null ? null : v ? 1 : 0);

export default function ReporteAgenda({
    filtros,
    opciones,
    filas,
    paginacion,
}: {
    filtros: Filtros;
    opciones: { tipos: Opcion[] };
    filas: Record<string, string | number | null>[];
    paginacion: Paginacion;
}) {
    const [desde, setDesde] = useState(filtros.desde ?? '');
    const [hasta, setHasta] = useState(filtros.hasta ?? '');
    const [tipo, setTipo] = useState<number | null>(filtros.tipo_evento_id);
    const [confirmado, setConfirmado] = useState<number | null>(
        triNum(filtros.confirmado),
    );
    const [discurso, setDiscurso] = useState<number | null>(
        triNum(filtros.discurso),
    );
    const [privado, setPrivado] = useState<number | null>(
        triNum(filtros.privado),
    );

    const params = {
        desde,
        hasta,
        tipo_evento_id: tipo,
        confirmado,
        discurso,
        privado,
    };

    return (
        <>
            <Head title="Reporte de agenda" />
            <ReporteShell
                title="Reporte de agenda"
                icon={CalendarDays}
                pageUrl="/reportes/agenda"
                exportBase="/reportes/agenda.xlsx"
                params={params}
                onLimpiar={() => {
                    setDesde('');
                    setHasta('');
                    setTipo(null);
                    setConfirmado(null);
                    setDiscurso(null);
                    setPrivado(null);
                }}
                columns={columnas}
                filas={filas}
                paginacion={paginacion}
            >
                <Field>
                    <Label htmlFor="desde">Desde</Label>
                    <Input
                        id="desde"
                        type="date"
                        value={desde}
                        onChange={(e) => setDesde(e.target.value)}
                    />
                </Field>
                <Field>
                    <Label htmlFor="hasta">Hasta</Label>
                    <Input
                        id="hasta"
                        type="date"
                        value={hasta}
                        onChange={(e) => setHasta(e.target.value)}
                    />
                </Field>
                <SelectField
                    label="Tipo de evento"
                    value={tipo}
                    options={opciones.tipos}
                    onChange={setTipo}
                />
                <SelectField
                    label="Confirmado"
                    value={confirmado}
                    options={TRI}
                    onChange={setConfirmado}
                />
                <SelectField
                    label="Con intervención"
                    value={discurso}
                    options={TRI}
                    onChange={setDiscurso}
                />
                <SelectField
                    label="Privado"
                    value={privado}
                    options={TRI}
                    onChange={setPrivado}
                />
            </ReporteShell>
        </>
    );
}

ReporteAgenda.layout = {
    breadcrumbs: [
        { title: 'Reportes', href: '/reportes/agenda' },
        { title: 'Agenda', href: '/reportes/agenda' },
    ],
};
