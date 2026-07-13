import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    MoreHorizontal,
    Pencil,
    Plus,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { DataTable } from '@/components/data-table';
import type { DataTableColumn, DataTableServer } from '@/components/data-table';
import { FormInputField } from '@/components/form-input-field';
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
import { destroy, index, store } from '@/routes/seguridad/denuncias';

type Opcion = { id: number; nombre: string };
type Denuncia = {
    id: number;
    denunciante: string;
    anonimo: boolean;
    tipo_nombre: string | null;
    sector_nombre: string | null;
    municipio_nombre: string | null;
    estatus_label: string;
    detenidos_count: number;
    [key: string]: string | number | boolean | null;
};
type Opciones = {
    origenes: Opcion[];
    tiposIncidencia: Opcion[];
    nivelesViolencia: Opcion[];
    sectores: Opcion[];
    estados: Opcion[];
};
type Form = Record<string, string | number | boolean | null>;

const VACIO: Form = {
    id: null,
    anonimo: false,
    denunciante_nombre: '',
    denunciante_paterno: '',
    denunciante_materno: '',
    fecha_denuncia: '',
    hora_denuncia: '',
    origen_denuncia_id: null,
    denuncia: '',
    descripcion_situacion: '',
    tipo_incidencia_id: null,
    nivel_violencia_id: null,
    seg_sector_id: null,
    estado_id: null,
    municipio_id: null,
    localidad_id: null,
    latitud: '',
    longitud: '',
    atendido_por: '',
    fecha_atencion: '',
    hora_atencion: '',
    acciones: '',
    acuerdos_convenios: '',
    conclusion: '',
    asignado: '',
    vehiculo: '',
    turnado: 0,
    con_atencion: false,
    con_termino: false,
};

const ESTATUS_VARIANT: Record<string, 'default' | 'secondary' | 'outline'> = {
    Recibida: 'secondary',
    Turnada: 'outline',
    'En atención': 'default',
    Concluida: 'default',
};

export default function DenunciasIndex({
    denuncias,
    paginacion,
    opciones,
}: {
    denuncias: Denuncia[];
    paginacion: DataTableServer;
    opciones: Opciones;
}) {
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [editando, setEditando] = useState<Denuncia | null>(null);
    const [eliminar, setEliminar] = useState<Denuncia | null>(null);
    const form = useForm<Form>(VACIO);
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
        form.setData(VACIO);
        setFormMode('create');
    };

    const abrirEditar = (d: Denuncia) => {
        setEditando(d);
        form.clearErrors();
        const datos: Form = { ...VACIO };

        for (const clave of Object.keys(VACIO)) {
            datos[clave] = d[clave] ?? (VACIO[clave] ?? '');
        }

        datos.id = d.id;
        form.setData(datos);
        setFormMode('edit');
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
                    resolveFormErrorMessage(errores, 'Revisa los campos.'),
                ),
        });
    };

    const texto = (clave: string, label: string, type = 'text') => (
        <FormInputField
            id={`den-${clave}`}
            type={type}
            label={label}
            value={(form.data[clave] as string) ?? ''}
            error={form.errors[clave]}
            onChange={(e) => form.setData(clave, e.target.value)}
        />
    );

    const columns: DataTableColumn<Denuncia>[] = [
        {
            key: 'denunciante',
            header: 'Denunciante',
            accessor: (row) => row.denunciante,
            cell: (row) => (
                <span className="font-medium">{row.denunciante}</span>
            ),
        },
        {
            key: 'tipo',
            header: 'Incidencia',
            cell: (row) => row.tipo_nombre || '—',
        },
        {
            key: 'sector',
            header: 'Sector',
            cell: (row) => row.sector_nombre || '—',
        },
        {
            key: 'estatus',
            header: 'Estatus',
            cell: (row) => (
                <Badge variant={ESTATUS_VARIANT[row.estatus_label] ?? 'secondary'}>
                    {row.estatus_label}
                </Badge>
            ),
        },
        {
            key: 'det',
            header: 'Det.',
            className: 'w-14 text-center',
            cell: (row) => row.detenidos_count,
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
                        <DropdownMenuItem onClick={() => abrirEditar(row)}>
                            <Pencil className="mr-2 size-4" /> Editar / atender
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
            <Head title="Denuncias" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <ShieldAlert className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">
                                    Denuncias y avisos
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Recepción, turnado y atención de denuncias.
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
                    data={denuncias}
                    server={paginacion}
                    searchPlaceholder="Buscar por denunciante, texto o quien atendió..."
                />
            </div>

            <CrudFormDialog
                open={formMode !== null}
                onOpenChange={(o) => {
                    if (!o) {
                        setFormMode(null);
                        setEditando(null);
                        form.clearErrors();
                    }
                }}
                size="xl"
                title={
                    formMode === 'edit' ? 'Editar denuncia' : 'Nueva denuncia'
                }
                description="Captura y atención de la denuncia."
                submitLabel={formMode === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={enviar}
            >
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={form.data.anonimo as boolean}
                        onCheckedChange={(v) =>
                            form.setData('anonimo', Boolean(v))
                        }
                    />
                    Denuncia anónima
                </label>

                {!form.data.anonimo && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('denunciante_nombre', 'Nombre(s)')}
                        {texto('denunciante_paterno', 'Apellido paterno')}
                        {texto('denunciante_materno', 'Apellido materno')}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('fecha_denuncia', 'Fecha de la denuncia')}
                    {texto('hora_denuncia', 'Hora')}
                    <SelectField
                        label="Origen"
                        value={form.data.origen_denuncia_id as number | null}
                        options={opciones.origenes}
                        error={form.errors.origen_denuncia_id}
                        onChange={(v) => form.setData('origen_denuncia_id', v)}
                    />
                    <SelectField
                        label="Tipo de incidencia"
                        value={form.data.tipo_incidencia_id as number | null}
                        options={opciones.tiposIncidencia}
                        error={form.errors.tipo_incidencia_id}
                        onChange={(v) => form.setData('tipo_incidencia_id', v)}
                    />
                    <SelectField
                        label="Nivel de violencia"
                        value={form.data.nivel_violencia_id as number | null}
                        options={opciones.nivelesViolencia}
                        error={form.errors.nivel_violencia_id}
                        onChange={(v) => form.setData('nivel_violencia_id', v)}
                    />
                    <SelectField
                        label="Sector"
                        value={form.data.seg_sector_id as number | null}
                        options={opciones.sectores}
                        error={form.errors.seg_sector_id}
                        onChange={(v) => form.setData('seg_sector_id', v)}
                    />
                </div>

                <Field>
                    <Label htmlFor="den-denuncia">Denuncia</Label>
                    <textarea
                        id="den-denuncia"
                        className="min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={(form.data.denuncia as string) ?? ''}
                        onChange={(e) => form.setData('denuncia', e.target.value)}
                    />
                </Field>

                <div className="space-y-2 border-t pt-4">
                    <p className="text-sm font-semibold text-muted-foreground">
                        Ubicación del hecho
                    </p>
                    <UbicacionSelects
                        estados={opciones.estados}
                        value={{
                            estado_id: form.data.estado_id as number | null,
                            municipio_id: form.data.municipio_id as number | null,
                            localidad_id: form.data.localidad_id as number | null,
                        }}
                        municipioNombre={editando?.municipio_nombre}
                        onChange={(patch) =>
                            form.setData({ ...form.data, ...patch })
                        }
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        {texto('latitud', 'Latitud')}
                        {texto('longitud', 'Longitud')}
                    </div>
                </div>

                <div className="space-y-3 border-t pt-4">
                    <p className="text-sm font-semibold text-muted-foreground">
                        Atención y seguimiento
                    </p>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('atendido_por', 'Atendido por')}
                        {texto('fecha_atencion', 'Fecha de atención')}
                        {texto('hora_atencion', 'Hora de atención')}
                        {texto('asignado', 'Asignado a')}
                        {texto('vehiculo', 'Vehículo')}
                    </div>
                    <Field>
                        <Label htmlFor="den-acciones">Acciones</Label>
                        <textarea
                            id="den-acciones"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={(form.data.acciones as string) ?? ''}
                            onChange={(e) =>
                                form.setData('acciones', e.target.value)
                            }
                        />
                    </Field>
                    <Field>
                        <Label htmlFor="den-conclusion">Conclusión</Label>
                        <textarea
                            id="den-conclusion"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={(form.data.conclusion as string) ?? ''}
                            onChange={(e) =>
                                form.setData('conclusion', e.target.value)
                            }
                        />
                    </Field>
                    <div className="flex flex-wrap gap-4">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={(form.data.turnado as number) > 0}
                                onCheckedChange={(v) =>
                                    form.setData('turnado', v ? 1 : 0)
                                }
                            />
                            Turnada
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.con_atencion as boolean}
                                onCheckedChange={(v) =>
                                    form.setData('con_atencion', Boolean(v))
                                }
                            />
                            En atención
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.con_termino as boolean}
                                onCheckedChange={(v) =>
                                    form.setData('con_termino', Boolean(v))
                                }
                            />
                            Concluida
                        </label>
                    </div>
                </div>
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar denuncia"
                entityLabel="la denuncia"
                itemName={eliminar?.denunciante}
                onConfirm={() => {
                    if (!eliminar) {
                        return;
                    }

                    router.delete(destroy.url(eliminar.id), {
                        preserveScroll: true,
                        onSuccess: () => setEliminar(null),
                        onError: () => toast.error('No se pudo eliminar.'),
                    });
                }}
            />
        </>
    );
}

DenunciasIndex.layout = {
    breadcrumbs: [
        { title: 'Seguridad', href: index() },
        { title: 'Denuncias', href: index() },
    ],
};
