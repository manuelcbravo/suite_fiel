import { router, useForm } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/date';
import { pesos, porcentaje } from '@/lib/money';

type Estimacion = {
    id: number | null;
    num_estimacion: string;
    tipo: number | null;
    superintendente: string;
    residente: string;
    fecha_recepcion: string | null;
    periodo_inicio: string | null;
    periodo_termino: string | null;
    monto: string;
    calidad_obra: string;
    estatus: string;
    monto_aprobado: string;
    fecha_aprobacion: string | null;
    num_factura: string;
    fecha_factura: string | null;
    importe_pagado: string;
    fecha_pago: string | null;
    avance_fisico: string;
    avance_financiero: string;
    doc_obligatoria: string;
    completo: boolean;
};

type Datos = {
    obra: { id: number; nombre: string | null; monto_contrato: string | null };
    catalogos: Record<string, Record<string, string>>;
    documentos: Record<string, string>;
    estimaciones: Estimacion[];
};

const VACIA: Estimacion = {
    id: null,
    num_estimacion: '',
    tipo: 1,
    superintendente: '',
    residente: '',
    fecha_recepcion: '',
    periodo_inicio: '',
    periodo_termino: '',
    monto: '',
    calidad_obra: '',
    estatus: '',
    monto_aprobado: '',
    fecha_aprobacion: '',
    num_factura: '',
    fecha_factura: '',
    importe_pagado: '',
    fecha_pago: '',
    avance_fisico: '',
    avance_financiero: '',
    doc_obligatoria: '',
    completo: false,
};

function comoOpciones(catalogo: Record<string, string> | undefined) {
    return Object.entries(catalogo ?? {}).map(([id, nombre]) => ({
        id: Number(id),
        nombre,
    }));
}

export function EstimacionesDialog({
    obraId,
    onClose,
}: {
    obraId: number | null;
    onClose: () => void;
}) {
    const [datos, setDatos] = useState<Datos | null>(null);
    const [editando, setEditando] = useState<Estimacion | null>(null);
    const form = useForm<Estimacion>(VACIA);

    const cargar = useCallback(() => {
        if (obraId === null) {
            return;
        }

        fetch(`/obra/${obraId}/estimaciones`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(r)))
            .then(setDatos)
            .catch(() =>
                toast.error('No se pudieron cargar las estimaciones.'),
            );
    }, [obraId]);

    useEffect(cargar, [cargar]);

    const guardar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (obraId === null) {
            return;
        }

        form.post(`/obra/${obraId}/estimaciones`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditando(null);
                cargar();
            },
            onError: () => toast.error('Revisa los campos de la estimación.'),
        });
    };

    const eliminar = (e: Estimacion) => {
        if (obraId === null || e.id === null) {
            return;
        }

        router.delete(`/obra/${obraId}/estimaciones/${e.id}`, {
            preserveScroll: true,
            onSuccess: cargar,
            onError: () => toast.error('No se pudo eliminar.'),
        });
    };

    const abrir = (e: Estimacion) => {
        form.clearErrors();
        form.setData({
            ...e,
            fecha_recepcion: e.fecha_recepcion ?? '',
            periodo_inicio: e.periodo_inicio ?? '',
            periodo_termino: e.periodo_termino ?? '',
            fecha_aprobacion: e.fecha_aprobacion ?? '',
            fecha_factura: e.fecha_factura ?? '',
            fecha_pago: e.fecha_pago ?? '',
        });
        setEditando(e);
    };

    const docsMarcados =
        form.data.doc_obligatoria === ''
            ? []
            : form.data.doc_obligatoria.split(',');

    const alternarDoc = (id: string) =>
        form.setData(
            'doc_obligatoria',
            (docsMarcados.includes(id)
                ? docsMarcados.filter((d) => d !== id)
                : [...docsMarcados, id]
            )
                .sort((a, b) => Number(a) - Number(b))
                .join(','),
        );

    const texto = (
        campo: keyof Estimacion,
        label: string,
        tipo: 'text' | 'date' = 'text',
    ) => (
        <Field>
            <Label>{label}</Label>
            <Input
                type={tipo}
                value={(form.data[campo] as string) ?? ''}
                aria-invalid={Boolean(form.errors[campo])}
                onChange={(e) => form.setData(campo, e.target.value)}
            />
            {form.errors[campo] && (
                <p className="text-xs text-destructive">{form.errors[campo]}</p>
            )}
        </Field>
    );

    return (
        <CrudFormDialog
            open={obraId !== null}
            onOpenChange={(o) => !o && onClose()}
            size="xl"
            title="Estimaciones"
            description={
                datos
                    ? `${datos.estimaciones.length} ${datos.estimaciones.length === 1 ? 'estimación' : 'estimaciones'}${datos.obra.monto_contrato ? ` · contrato por ${pesos(datos.obra.monto_contrato)}` : ''}.`
                    : 'Cargando…'
            }
            showFooter={false}
        >
            {datos === null ? (
                <p className="py-12 text-center text-sm text-muted-foreground">
                    Cargando las estimaciones…
                </p>
            ) : editando !== null ? (
                <form className="space-y-4" onSubmit={guardar}>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('num_estimacion', 'Número de estimación')}
                        <SelectField
                            label="Tipo"
                            value={form.data.tipo}
                            options={comoOpciones(datos.catalogos.tipo)}
                            onChange={(v) => form.setData('tipo', v)}
                        />
                        {texto('fecha_recepcion', 'Fecha de recepción', 'date')}
                        {texto('periodo_inicio', 'Periodo desde', 'date')}
                        {texto('periodo_termino', 'Periodo hasta', 'date')}
                        {texto('monto', 'Monto estimado')}
                        {texto('superintendente', 'Superintendente')}
                        {texto('residente', 'Residente')}
                        <SelectField
                            label="Calidad de la obra"
                            value={
                                form.data.calidad_obra === ''
                                    ? null
                                    : Number(form.data.calidad_obra)
                            }
                            options={comoOpciones(datos.catalogos.calidad_obra)}
                            onChange={(v) =>
                                form.setData(
                                    'calidad_obra',
                                    v === null ? '' : String(v),
                                )
                            }
                        />
                        <SelectField
                            label="Estatus"
                            value={
                                form.data.estatus === ''
                                    ? null
                                    : Number(form.data.estatus)
                            }
                            options={comoOpciones(datos.catalogos.estatus)}
                            onChange={(v) =>
                                form.setData(
                                    'estatus',
                                    v === null ? '' : String(v),
                                )
                            }
                        />
                        {texto('monto_aprobado', 'Monto aprobado')}
                        {texto(
                            'fecha_aprobacion',
                            'Fecha de aprobación',
                            'date',
                        )}
                        {texto('num_factura', 'Número de factura')}
                        {texto('fecha_factura', 'Fecha de la factura', 'date')}
                        {texto('importe_pagado', 'Importe pagado')}
                        {texto('fecha_pago', 'Fecha del pago', 'date')}
                        {texto('avance_fisico', 'Avance físico')}
                        {texto('avance_financiero', 'Avance financiero')}
                    </div>

                    <Field>
                        <Label>Documentación obligatoria</Label>
                        <div className="grid gap-x-6 gap-y-2 rounded-md border p-3 sm:grid-cols-2">
                            {Object.entries(datos.documentos).map(
                                ([id, label]) => (
                                    <label
                                        key={id}
                                        className="flex cursor-pointer items-start gap-2 text-sm"
                                    >
                                        <Checkbox
                                            className="mt-0.5 shrink-0"
                                            checked={docsMarcados.includes(id)}
                                            onCheckedChange={() =>
                                                alternarDoc(id)
                                            }
                                        />
                                        <span className="min-w-0">{label}</span>
                                    </label>
                                ),
                            )}
                        </div>
                    </Field>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.completo}
                            onCheckedChange={(v) =>
                                form.setData('completo', Boolean(v))
                            }
                        />
                        Dar por cerrada la estimación
                    </label>

                    <div className="flex justify-end gap-2 border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEditando(null)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {editando.id === null
                                ? 'Agregar estimación'
                                : 'Guardar cambios'}
                        </Button>
                    </div>
                </form>
            ) : (
                <div className="space-y-3">
                    <Button
                        variant="outline"
                        onClick={() => abrir({ ...VACIA })}
                    >
                        <Plus className="mr-2 size-4" /> Nueva estimación
                    </Button>

                    {datos.estimaciones.length === 0 ? (
                        <p className="rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
                            Esta obra todavía no tiene estimaciones.
                        </p>
                    ) : (
                        <div className="divide-y divide-sidebar-border/70 overflow-hidden rounded-xl border border-sidebar-border/70">
                            {datos.estimaciones.map((e) => (
                                <div
                                    key={e.id}
                                    className="flex items-start gap-3 p-3"
                                >
                                    <button
                                        type="button"
                                        className="min-w-0 flex-1 text-left"
                                        onClick={() => abrir(e)}
                                    >
                                        <p className="text-sm font-medium">
                                            Estimación{' '}
                                            {e.num_estimacion || `#${e.id}`}
                                            {e.tipo === 2 &&
                                                ' · extraordinaria'}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {e.periodo_inicio &&
                                            e.periodo_termino
                                                ? `Del ${formatDate(e.periodo_inicio)} al ${formatDate(e.periodo_termino)}`
                                                : 'Sin periodo capturado'}
                                            {e.monto && ` · ${pesos(e.monto)}`}
                                            {e.avance_fisico &&
                                                ` · avance ${porcentaje(e.avance_fisico)}`}
                                        </p>
                                    </button>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8 shrink-0"
                                            >
                                                <MoreHorizontal className="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                onClick={() => abrir(e)}
                                            >
                                                <Pencil className="mr-2 size-4" />{' '}
                                                Editar
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onClick={() => eliminar(e)}
                                            >
                                                <Trash2 className="mr-2 size-4" />{' '}
                                                Eliminar
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </CrudFormDialog>
    );
}
