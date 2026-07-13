import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    Eye,
    MoreHorizontal,
    Pencil,
    Plus,
    Send,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AsyncSearchPicker } from '@/components/async-search-picker';
import { BeneficiarioPicker } from '@/components/beneficiario-picker';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { DataTable } from '@/components/data-table';
import type { DataTableColumn, DataTableServer } from '@/components/data-table';
import { FormInputField } from '@/components/form-input-field';
import { MultiCheckField } from '@/components/multi-check-field';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Badge } from '@/components/ui/badge';
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
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, index, store } from '@/routes/gestion/solicitudes';

type Opcion = { id: number; nombre: string };
type AreaOpcion = Opcion & { dependencia_id: number | null };
type Solicitud = {
    id: number;
    folio: string | null;
    folio_sistema: string | null;
    solicitud: string | null;
    status: number;
    status_label: string;
    control_administrativo: boolean;
    prioridad: number | null;
    fecha_recepcion: string | null;
    concepto_id: number | null;
    procedencia_id: number | null;
    apoyo: string | null;
    cantidad: string | null;
    monto: string | null;
    desc_bene: string | null;
    num_bene: string | null;
    solicitante_id: number | null;
    solicitante_tipo: string | null;
    solicitante_nombre: string | null;
    municipio_resp_nombre: string | null;
    estado_resp_id: number | null;
    municipio_resp_id: number | null;
    localidad_resp_id: number | null;
    unidad_medida_id: number | null;
    origen_recurso_id: number | null;
    folio_resp: string | null;
    fecha_resp: string | null;
    rubros: number[];
    sectores: number[];
    seguimientos_count: number;
};
type Seguimiento = {
    id: number;
    estatus: number | null;
    estatus_label: string;
    dependencia_id: number | null;
    dependencia: string | null;
    area_id: number | null;
    area: string | null;
    instruccion: string | null;
    respuesta: string | null;
    responsable: string | null;
    avance: number | null;
    fecha: string | null;
    fecha_respuesta: string | null;
};
type Verificacion = {
    id: number;
    fecha: string | null;
    atendido: number | null;
    satisfecho_label: string | null;
    comentario: string | null;
    autor: string | null;
};
type Opciones = {
    estatus: Opcion[];
    conceptos: Opcion[];
    procedencias: Opcion[];
    rubros: Opcion[];
    sectores: Opcion[];
    estados: Opcion[];
    dependencias: Opcion[];
    areas: AreaOpcion[];
    unidadesMedida: Opcion[];
    origenesRecurso: Opcion[];
};
type Form = Record<string, string | number | boolean | number[] | null>;

const STATUS_VARIANT: Record<
    number,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    0: 'secondary',
    1: 'default',
    2: 'destructive',
    3: 'outline',
    4: 'outline',
    5: 'default',
    6: 'default',
};

const CAMPOS_INICIALES: Form = {
    id: null,
    solicitante_tipo: 'beneficiario',
    beneficiario_id: null,
    organizacion_id: null,
    folio: '',
    folio_sistema: '',
    solicitud: '',
    apoyo: '',
    desc_bene: '',
    cantidad: '',
    monto: '',
    num_bene: '',
    concepto_id: null,
    procedencia_id: null,
    origen: '',
    status: 0,
    control_administrativo: false,
    prioridad: null,
    fecha_recepcion: '',
    estado_resp_id: null,
    municipio_resp_id: null,
    localidad_resp_id: null,
    rubros: [],
    sectores: [],
};

export default function SolicitudesIndex({
    solicitudes,
    paginacion,
    opciones,
}: {
    solicitudes: Solicitud[];
    paginacion: DataTableServer;
    opciones: Opciones;
}) {
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [editando, setEditando] = useState<Solicitud | null>(null);
    const [eliminar, setEliminar] = useState<Solicitud | null>(null);
    const [detalle, setDetalle] = useState<Solicitud | null>(null);
    const form = useForm<Form>(CAMPOS_INICIALES);
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const abrirCrear = () => {
        setEditando(null);
        form.clearErrors();
        form.setData(CAMPOS_INICIALES);
        setFormMode('create');
    };

    const abrirEditar = (s: Solicitud) => {
        setEditando(s);
        form.clearErrors();
        const esOrg = s.solicitante_tipo === 'Organizacion';
        form.setData({
            id: s.id,
            solicitante_tipo: esOrg ? 'organizacion' : 'beneficiario',
            beneficiario_id: esOrg ? null : (s.solicitante_id ?? null),
            organizacion_id: esOrg ? (s.solicitante_id ?? null) : null,
            folio: s.folio ?? '',
            folio_sistema: s.folio_sistema ?? '',
            solicitud: s.solicitud ?? '',
            apoyo: s.apoyo ?? '',
            desc_bene: s.desc_bene ?? '',
            cantidad: s.cantidad ?? '',
            monto: s.monto ?? '',
            num_bene: s.num_bene ?? '',
            concepto_id: s.concepto_id,
            procedencia_id: s.procedencia_id,
            origen: '',
            status: s.status,
            control_administrativo: s.control_administrativo,
            prioridad: s.prioridad,
            fecha_recepcion: s.fecha_recepcion ?? '',
            estado_resp_id: s.estado_resp_id,
            municipio_resp_id: s.municipio_resp_id,
            localidad_resp_id: s.localidad_resp_id,
            rubros: s.rubros,
            sectores: s.sectores,
        });
        setFormMode('edit');
    };

    const cerrar = (open: boolean) => {
        if (!open) {
            setFormMode(null);
            setEditando(null);
            form.clearErrors();
        }
    };

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setFormMode(null);
                setEditando(null);
                form.reset();
            },
            onError: (errores) =>
                toast.error(
                    resolveFormErrorMessage(
                        errores,
                        'Verifica los campos marcados.',
                    ),
                ),
        });
    };

    const texto = (clave: string, label: string) => (
        <FormInputField
            id={`sol-${clave}`}
            label={label}
            value={(form.data[clave] as string) ?? ''}
            error={form.errors[clave]}
            onChange={(e) => form.setData(clave, e.target.value)}
        />
    );

    const columns: DataTableColumn<Solicitud>[] = [
        {
            key: 'folio',
            header: 'Folio',
            accessor: (row) => row.folio,
            cell: (row) => (
                <span className="font-medium">
                    {row.folio || row.folio_sistema || `#${row.id}`}
                </span>
            ),
        },
        {
            key: 'solicitante',
            header: 'Solicitante',
            cell: (row) => row.solicitante_nombre || '—',
        },
        {
            key: 'solicitud',
            header: 'Solicitud',
            cell: (row) => (
                <span className="line-clamp-1 max-w-xs text-sm text-muted-foreground">
                    {row.solicitud || '—'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Estatus',
            cell: (row) => (
                <Badge variant={STATUS_VARIANT[row.status] ?? 'secondary'}>
                    {row.status_label}
                </Badge>
            ),
        },
        {
            key: 'seg',
            header: 'Seg.',
            className: 'w-14 text-center',
            cell: (row) => row.seguimientos_count,
        },
        {
            key: 'actions',
            header: '',
            className: 'w-14',
            cell: (row) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-8">
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={() => setDetalle(row)}>
                            <Eye className="mr-2 size-4" /> Ver / turnar
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => abrirEditar(row)}>
                            <Pencil className="mr-2 size-4" /> Editar
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={() => setEliminar(row)}
                        >
                            <Trash2 className="mr-2 size-4" /> Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <>
            <Head title="Solicitudes" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <ClipboardList className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">
                                    Solicitudes
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Gestión de solicitudes y su turnado.
                                </p>
                            </div>
                        </div>
                        <Button onClick={abrirCrear}>
                            <Plus className="mr-2 size-4" /> Nueva
                        </Button>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={solicitudes}
                    server={paginacion}
                    searchPlaceholder="Buscar por folio o descripción..."
                />
            </div>

            {/* Alta / edición */}
            <CrudFormDialog
                open={formMode !== null}
                onOpenChange={cerrar}
                size="xl"
                title={
                    formMode === 'edit' ? 'Editar solicitud' : 'Nueva solicitud'
                }
                description="Captura los datos de la solicitud."
                submitLabel={formMode === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={enviar}
            >
                <Field>
                    <Label>Tipo de solicitante</Label>
                    <div className="flex gap-2">
                        {(
                            [
                                ['beneficiario', 'Beneficiario'],
                                ['organizacion', 'Organización'],
                            ] as const
                        ).map(([valor, etiqueta]) => (
                            <Button
                                key={valor}
                                type="button"
                                variant={
                                    form.data.solicitante_tipo === valor
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                onClick={() =>
                                    form.setData({
                                        ...form.data,
                                        solicitante_tipo: valor,
                                        beneficiario_id: null,
                                        organizacion_id: null,
                                    })
                                }
                            >
                                {etiqueta}
                            </Button>
                        ))}
                    </div>
                </Field>

                {form.data.solicitante_tipo === 'organizacion' ? (
                    <AsyncSearchPicker
                        label="Solicitante (organización)"
                        searchUrl="/directorio/organizaciones/buscar"
                        placeholder="Buscar organización (mín. 2 letras)..."
                        value={form.data.organizacion_id as number | null}
                        valueLabel={
                            editando?.solicitante_tipo === 'Organizacion'
                                ? editando.solicitante_nombre
                                : null
                        }
                        error={form.errors.organizacion_id}
                        onChange={(id) => form.setData('organizacion_id', id)}
                    />
                ) : (
                    <BeneficiarioPicker
                        label="Solicitante (beneficiario)"
                        value={form.data.beneficiario_id as number | null}
                        valueLabel={
                            editando?.solicitante_tipo !== 'Organizacion'
                                ? editando?.solicitante_nombre
                                : null
                        }
                        error={form.errors.beneficiario_id}
                        onChange={(id) => form.setData('beneficiario_id', id)}
                    />
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    {texto('folio', 'Folio')}
                    {texto('fecha_recepcion', 'Fecha de recepción')}
                </div>

                <Field>
                    <Label htmlFor="sol-solicitud">Descripción</Label>
                    <textarea
                        id="sol-solicitud"
                        className="min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={(form.data.solicitud as string) ?? ''}
                        onChange={(e) => form.setData('solicitud', e.target.value)}
                    />
                </Field>

                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('apoyo', 'Apoyo')}
                    {texto('cantidad', 'Cantidad')}
                    {texto('monto', 'Monto')}
                    <SelectField
                        label="Concepto"
                        value={form.data.concepto_id as number | null}
                        options={opciones.conceptos}
                        error={form.errors.concepto_id}
                        onChange={(v) => form.setData('concepto_id', v)}
                    />
                    <SelectField
                        label="Procedencia"
                        value={form.data.procedencia_id as number | null}
                        options={opciones.procedencias}
                        error={form.errors.procedencia_id}
                        onChange={(v) => form.setData('procedencia_id', v)}
                    />
                    <SelectField
                        label="Estatus"
                        value={form.data.status as number | null}
                        options={opciones.estatus}
                        error={form.errors.status}
                        onChange={(v) => form.setData('status', v ?? 0)}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <MultiCheckField
                        label="Rubros"
                        options={opciones.rubros}
                        value={form.data.rubros as number[]}
                        error={form.errors.rubros}
                        onChange={(v) => form.setData('rubros', v)}
                    />
                    <MultiCheckField
                        label="Sectores"
                        options={opciones.sectores}
                        value={form.data.sectores as number[]}
                        error={form.errors.sectores}
                        onChange={(v) => form.setData('sectores', v)}
                    />
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.control_administrativo as boolean}
                        onCheckedChange={(v) =>
                            form.setData('control_administrativo', Boolean(v))
                        }
                    />
                    Solicitud de control administrativo
                </label>

                <div className="space-y-2 border-t pt-4">
                    <p className="text-sm font-semibold text-muted-foreground">
                        Ubicación de la respuesta
                    </p>
                    <UbicacionSelects
                        estados={opciones.estados}
                        value={{
                            estado_id: form.data.estado_resp_id as number | null,
                            municipio_id: form.data
                                .municipio_resp_id as number | null,
                            localidad_id: form.data
                                .localidad_resp_id as number | null,
                        }}
                        municipioNombre={editando?.municipio_resp_nombre}
                        onChange={(patch) =>
                            form.setData({
                                ...form.data,
                                estado_resp_id: patch.estado_id ?? null,
                                municipio_resp_id: patch.municipio_id ?? null,
                                localidad_resp_id: patch.localidad_id ?? null,
                            })
                        }
                    />
                </div>
            </CrudFormDialog>

            {/* Detalle + seguimientos */}
            {detalle && (
                <DetalleSolicitud
                    solicitud={detalle}
                    opciones={opciones}
                    onClose={() => setDetalle(null)}
                />
            )}

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar solicitud"
                entityLabel="la solicitud"
                itemName={eliminar?.folio ?? `#${eliminar?.id}`}
                onConfirm={() => {
                    if (!eliminar) {
                        return;
                    }

                    router.delete(destroy.url(eliminar.id), {
                        preserveScroll: true,
                        onSuccess: () => setEliminar(null),
                        onError: (errores) =>
                            toast.error(
                                resolveFormErrorMessage(
                                    errores,
                                    'No se pudo eliminar.',
                                ),
                            ),
                    });
                }}
            />
        </>
    );
}

function DetalleSolicitud({
    solicitud,
    opciones,
    onClose,
}: {
    solicitud: Solicitud;
    opciones: Opciones;
    onClose: () => void;
}) {
    const [seguimientos, setSeguimientos] = useState<Seguimiento[]>([]);
    const [verificaciones, setVerificaciones] = useState<Verificacion[]>([]);
    const turnar = useForm<{
        dependencia_id: number | null;
        area_id: number | null;
        instruccion: string;
    }>({ dependencia_id: null, area_id: null, instruccion: '' });
    const verif = useForm<{ comentario: string; atendido: number | null }>({
        comentario: '',
        atendido: null,
    });

    const cargar = () => {
        fetch(`/gestion/solicitudes/${solicitud.id}/seguimientos`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => setSeguimientos(data as Seguimiento[]));
    };

    const cargarVerif = () => {
        fetch(`/gestion/solicitudes/${solicitud.id}/verificaciones`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => setVerificaciones(data as Verificacion[]));
    };

    useEffect(() => {
        cargar();
        cargarVerif();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [solicitud.id]);

    const enviarVerif = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        verif.post(`/gestion/solicitudes/${solicitud.id}/verificaciones`, {
            preserveScroll: true,
            onSuccess: () => {
                verif.reset();
                cargarVerif();
            },
            onError: () => toast.error('Revisa la verificación.'),
        });
    };

    const areasFiltradas = opciones.areas.filter(
        (a) =>
            turnar.data.dependencia_id === null ||
            a.dependencia_id === turnar.data.dependencia_id,
    );

    const enviarTurnado = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        turnar.post(`/gestion/solicitudes/${solicitud.id}/seguimientos`, {
            preserveScroll: true,
            onSuccess: () => {
                turnar.reset();
                cargar();
            },
            onError: () => toast.error('Revisa los datos del turnado.'),
        });
    };

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="lg"
            showFooter={false}
            title={`Solicitud ${solicitud.folio || `#${solicitud.id}`}`}
            description={solicitud.solicitante_nombre ?? ''}
        >
            <div className="space-y-4">
                <div className="rounded-md border p-3 text-sm">
                    <p className="mb-1">
                        <Badge
                            variant={
                                STATUS_VARIANT[solicitud.status] ?? 'secondary'
                            }
                        >
                            {solicitud.status_label}
                        </Badge>
                    </p>
                    <p className="text-muted-foreground">
                        {solicitud.solicitud || 'Sin descripción.'}
                    </p>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-semibold">
                        Seguimientos ({seguimientos.length})
                    </h3>
                    <div className="space-y-2">
                        {seguimientos.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Sin seguimientos aún.
                            </p>
                        )}
                        {seguimientos.map((sg) => (
                            <SeguimientoItem
                                key={sg.id}
                                seguimiento={sg}
                                areas={opciones.areas}
                                onCambio={cargar}
                            />
                        ))}
                    </div>
                </div>

                <form
                    className="space-y-3 border-t pt-4"
                    onSubmit={enviarTurnado}
                >
                    <p className="text-sm font-semibold">Turnar a dependencia</p>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <SelectField
                            label="Dependencia"
                            value={turnar.data.dependencia_id}
                            options={opciones.dependencias}
                            error={turnar.errors.dependencia_id}
                            onChange={(v) => {
                                turnar.setData('dependencia_id', v);
                                turnar.setData('area_id', null);
                            }}
                        />
                        <SelectField
                            label="Área"
                            value={turnar.data.area_id}
                            options={areasFiltradas}
                            onChange={(v) => turnar.setData('area_id', v)}
                            disabled={turnar.data.dependencia_id === null}
                        />
                    </div>
                    <Field>
                        <Label htmlFor="turnar-instr">Instrucción</Label>
                        <textarea
                            id="turnar-instr"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={turnar.data.instruccion}
                            onChange={(e) =>
                                turnar.setData('instruccion', e.target.value)
                            }
                        />
                    </Field>
                    <Button type="submit" disabled={turnar.processing}>
                        <Send className="mr-2 size-4" /> Turnar
                    </Button>
                </form>

                <AtenderSolicitud solicitud={solicitud} opciones={opciones} />

                <div className="border-t pt-4">
                    <h3 className="mb-2 text-sm font-semibold">
                        Verificaciones ({verificaciones.length})
                    </h3>
                    <div className="space-y-2">
                        {verificaciones.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Sin verificaciones.
                            </p>
                        )}
                        {verificaciones.map((v) => (
                            <div
                                key={v.id}
                                className="rounded-md border p-3 text-sm"
                            >
                                <p className="whitespace-pre-wrap">
                                    {v.comentario}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {v.fecha ?? ''}
                                    {v.satisfecho_label
                                        ? ` · Satisfacción: ${v.satisfecho_label}`
                                        : ''}
                                    {v.autor ? ` · ${v.autor}` : ''}
                                </p>
                            </div>
                        ))}
                    </div>
                    <form className="mt-3 space-y-2" onSubmit={enviarVerif}>
                        <textarea
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            placeholder="Registrar verificación / seguimiento de satisfacción..."
                            value={verif.data.comentario}
                            onChange={(e) =>
                                verif.setData('comentario', e.target.value)
                            }
                        />
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={verif.processing}
                        >
                            Registrar verificación
                        </Button>
                    </form>
                </div>
            </div>
        </CrudFormDialog>
    );
}

function SeguimientoItem({
    seguimiento,
    areas,
    onCambio,
}: {
    seguimiento: Seguimiento;
    areas: AreaOpcion[];
    onCambio: () => void;
}) {
    const [respondiendo, setRespondiendo] = useState(false);
    const [reasignando, setReasignando] = useState(false);
    const responder = useForm<{ respuesta: string }>({ respuesta: '' });
    const reasignar = useForm<{ area_id: number | null }>({
        area_id: seguimiento.area_id,
    });

    // Solo direcciones de la dependencia a la que se turnó (drccn legacy).
    const areasDependencia = areas.filter(
        (a) =>
            seguimiento.dependencia_id === null ||
            a.dependencia_id === seguimiento.dependencia_id,
    );

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        responder.put(`/gestion/seguimientos/${seguimiento.id}/responder`, {
            preserveScroll: true,
            onSuccess: () => {
                setRespondiendo(false);
                responder.reset();
                onCambio();
            },
            onError: () => toast.error('No se pudo registrar la respuesta.'),
        });
    };

    const enviarReasignacion = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        reasignar.put(`/gestion/seguimientos/${seguimiento.id}/reasignar`, {
            preserveScroll: true,
            onSuccess: () => {
                setReasignando(false);
                onCambio();
            },
            onError: () =>
                toast.error('No se pudo reasignar la dirección.'),
        });
    };

    return (
        <div className="rounded-md border p-3 text-sm">
            <div className="flex items-center justify-between gap-2">
                <span className="font-medium">
                    {seguimiento.dependencia || 'Sin dependencia'}
                    {seguimiento.area ? ` · ${seguimiento.area}` : ''}
                </span>
                <div className="flex items-center gap-2">
                    {!seguimiento.respuesta && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-7 px-2 text-xs"
                            onClick={() => setReasignando((v) => !v)}
                        >
                            Reasignar dirección
                        </Button>
                    )}
                    <Badge variant="outline">{seguimiento.estatus_label}</Badge>
                </div>
            </div>
            {reasignando && (
                <form
                    className="mt-2 flex items-end gap-2 border-t pt-2"
                    onSubmit={enviarReasignacion}
                >
                    <div className="flex-1">
                        <SelectField
                            label="Dirección (área)"
                            value={reasignar.data.area_id}
                            options={areasDependencia}
                            error={reasignar.errors.area_id}
                            onChange={(v) => reasignar.setData('area_id', v)}
                        />
                    </div>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={
                            reasignar.processing ||
                            reasignar.data.area_id === null
                        }
                    >
                        Actualizar
                    </Button>
                </form>
            )}
            {seguimiento.instruccion && (
                <p className="mt-1 text-muted-foreground">
                    Instrucción: {seguimiento.instruccion}
                </p>
            )}
            {seguimiento.respuesta ? (
                <p className="mt-1">
                    <span className="font-medium">Respuesta:</span>{' '}
                    {seguimiento.respuesta}
                </p>
            ) : respondiendo ? (
                <form className="mt-2 space-y-2" onSubmit={enviar}>
                    <textarea
                        className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        placeholder="Respuesta..."
                        value={responder.data.respuesta}
                        onChange={(e) =>
                            responder.setData('respuesta', e.target.value)
                        }
                    />
                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={responder.processing}
                        >
                            Guardar respuesta
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setRespondiendo(false)}
                        >
                            Cancelar
                        </Button>
                    </div>
                </form>
            ) : (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="mt-2"
                    onClick={() => setRespondiendo(true)}
                >
                    Responder
                </Button>
            )}
        </div>
    );
}

function AtenderSolicitud({
    solicitud,
    opciones,
}: {
    solicitud: Solicitud;
    opciones: Opciones;
}) {
    const form = useForm<Record<string, string | number | number[] | null>>({
        decision: 'resolver',
        respuesta: '',
        avance: '',
        apoyo: solicitud.apoyo ?? '',
        cantidad: solicitud.cantidad ?? '',
        unidad_medida_id: solicitud.unidad_medida_id,
        monto: solicitud.monto ?? '',
        num_bene: solicitud.num_bene ?? '',
        concepto_id: solicitud.concepto_id,
        origen_recurso_id: solicitud.origen_recurso_id,
        rubros: solicitud.rubros ?? [],
        sectores: solicitud.sectores ?? [],
        estado_resp_id: solicitud.estado_resp_id,
        municipio_resp_id: solicitud.municipio_resp_id,
        localidad_resp_id: solicitud.localidad_resp_id,
        folio_resp: solicitud.folio_resp ?? '',
        fecha_resp: solicitud.fecha_resp ?? '',
    });

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/gestion/solicitudes/${solicitud.id}/atender`, {
            preserveScroll: true,
            onError: () => toast.error('Revisa los datos de la atención.'),
        });
    };

    const completo = form.data.decision !== 'temporal';

    return (
        <form className="space-y-3 border-t pt-4" onSubmit={enviar}>
            <p className="text-sm font-semibold">Atender / resolver</p>

            <Field>
                <Label htmlFor="atn-decision">Decisión</Label>
                <select
                    id="atn-decision"
                    className="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                    value={form.data.decision as string}
                    onChange={(e) => form.setData('decision', e.target.value)}
                >
                    <option value="temporal">Respuesta temporal (avance)</option>
                    <option value="rapida">Atención rápida</option>
                    <option value="resolver">Atendida / resuelta</option>
                </select>
            </Field>

            <Field>
                <Label htmlFor="atn-respuesta">Respuesta</Label>
                <textarea
                    id="atn-respuesta"
                    className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                    value={form.data.respuesta as string}
                    onChange={(e) => form.setData('respuesta', e.target.value)}
                />
            </Field>

            {form.data.decision === 'temporal' && (
                <FormInputField
                    id="atn-avance"
                    type="number"
                    label="Avance (%)"
                    value={(form.data.avance as string) ?? ''}
                    onChange={(e) => form.setData('avance', e.target.value)}
                />
            )}

            {completo && (
                <>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <FormInputField
                            id="atn-apoyo"
                            label="Apoyo"
                            value={(form.data.apoyo as string) ?? ''}
                            onChange={(e) =>
                                form.setData('apoyo', e.target.value)
                            }
                        />
                        <FormInputField
                            id="atn-cantidad"
                            label="Cantidad"
                            value={(form.data.cantidad as string) ?? ''}
                            onChange={(e) =>
                                form.setData('cantidad', e.target.value)
                            }
                        />
                        <SelectField
                            label="Unidad"
                            value={form.data.unidad_medida_id as number | null}
                            options={opciones.unidadesMedida}
                            onChange={(v) => form.setData('unidad_medida_id', v)}
                        />
                        <FormInputField
                            id="atn-monto"
                            label="Monto"
                            value={(form.data.monto as string) ?? ''}
                            onChange={(e) =>
                                form.setData('monto', e.target.value)
                            }
                        />
                        <FormInputField
                            id="atn-numbene"
                            label="Núm. beneficiarios"
                            value={(form.data.num_bene as string) ?? ''}
                            onChange={(e) =>
                                form.setData('num_bene', e.target.value)
                            }
                        />
                        <SelectField
                            label="Concepto"
                            value={form.data.concepto_id as number | null}
                            options={opciones.conceptos}
                            onChange={(v) => form.setData('concepto_id', v)}
                        />
                        <SelectField
                            label="Origen del recurso"
                            value={form.data.origen_recurso_id as number | null}
                            options={opciones.origenesRecurso}
                            onChange={(v) =>
                                form.setData('origen_recurso_id', v)
                            }
                        />
                        <FormInputField
                            id="atn-folioresp"
                            label="Folio de respuesta"
                            value={(form.data.folio_resp as string) ?? ''}
                            onChange={(e) =>
                                form.setData('folio_resp', e.target.value)
                            }
                        />
                        <FormInputField
                            id="atn-fecharesp"
                            type="date"
                            label="Fecha de respuesta"
                            value={(form.data.fecha_resp as string) ?? ''}
                            onChange={(e) =>
                                form.setData('fecha_resp', e.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <MultiCheckField
                            label="Rubros"
                            options={opciones.rubros}
                            value={form.data.rubros as number[]}
                            onChange={(v) => form.setData('rubros', v)}
                        />
                        <MultiCheckField
                            label="Sectores"
                            options={opciones.sectores}
                            value={form.data.sectores as number[]}
                            onChange={(v) => form.setData('sectores', v)}
                        />
                    </div>

                    <div>
                        <p className="mb-1 text-xs font-medium text-muted-foreground">
                            Ubicación de la respuesta
                        </p>
                        <UbicacionSelects
                            estados={opciones.estados}
                            value={{
                                estado_id: form.data
                                    .estado_resp_id as number | null,
                                municipio_id: form.data
                                    .municipio_resp_id as number | null,
                                localidad_id: form.data
                                    .localidad_resp_id as number | null,
                            }}
                            onChange={(patch) =>
                                form.setData({
                                    ...form.data,
                                    ...('estado_id' in patch
                                        ? { estado_resp_id: patch.estado_id }
                                        : {}),
                                    ...('municipio_id' in patch
                                        ? {
                                              municipio_resp_id:
                                                  patch.municipio_id,
                                          }
                                        : {}),
                                    ...('localidad_id' in patch
                                        ? {
                                              localidad_resp_id:
                                                  patch.localidad_id,
                                          }
                                        : {}),
                                })
                            }
                        />
                    </div>
                </>
            )}

            <Button type="submit" disabled={form.processing}>
                Registrar atención
            </Button>
        </form>
    );
}

SolicitudesIndex.layout = {
    breadcrumbs: [
        { title: 'Gestión', href: index() },
        { title: 'Solicitudes', href: index() },
    ],
};
