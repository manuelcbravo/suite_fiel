import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fingerprint, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AsyncSearchPicker } from '@/components/async-search-picker';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { DataTable } from '@/components/data-table';
import type { DataTableColumn, DataTableServer } from '@/components/data-table';
import { FormInputField } from '@/components/form-input-field';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Button } from '@/components/ui/button';
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
import { destroy, index, store } from '@/routes/seguridad/detenidos';

type Opcion = { id: number; nombre: string };
type Detenido = {
    id: number;
    nombre_completo: string;
    alias: string | null;
    edad: number | null;
    motivo_retencion: string | null;
    denuncia_id: number | null;
    denuncia_label: string | null;
    ocupacion_nombre: string | null;
    [key: string]: string | number | null;
};
type Opciones = {
    ocupaciones: Opcion[];
    estadosCiviles: Opcion[];
    estados: Opcion[];
};
type Form = Record<string, string | number | null>;

const SEXOS: Opcion[] = [
    { id: 1, nombre: 'Masculino' },
    { id: 2, nombre: 'Femenino' },
];

const VACIO: Form = {
    id: null,
    denuncia_id: null,
    nombre: '',
    paterno: '',
    materno: '',
    alias: '',
    edad: null,
    fecha_nac: '',
    sexo: null,
    nacionalidad: '',
    lugar_nac: '',
    ocupacion_id: null,
    estado_civil_id: null,
    estado_id: null,
    municipio_id: null,
    direccion: '',
    celular: '',
    telefono: '',
    lugar_retencion: '',
    fecha_retencion: '',
    padre_nombre: '',
    madre_nombre: '',
    motivo_retencion: '',
    observaciones: '',
};

export default function DetenidosIndex({
    detenidos,
    paginacion,
    opciones,
}: {
    detenidos: Detenido[];
    paginacion: DataTableServer;
    opciones: Opciones;
}) {
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [editando, setEditando] = useState<Detenido | null>(null);
    const [eliminar, setEliminar] = useState<Detenido | null>(null);
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

    const abrirEditar = (d: Detenido) => {
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
            id={`det-${clave}`}
            type={type}
            label={label}
            value={(form.data[clave] as string) ?? ''}
            error={form.errors[clave]}
            onChange={(e) => form.setData(clave, e.target.value)}
        />
    );

    const columns: DataTableColumn<Detenido>[] = [
        {
            key: 'nombre_completo',
            header: 'Detenido',
            accessor: (row) => row.nombre_completo,
            cell: (row) => (
                <div>
                    <div className="font-medium">{row.nombre_completo}</div>
                    {row.alias ? (
                        <div className="text-xs text-muted-foreground">
                            {row.alias}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'edad',
            header: 'Edad',
            className: 'w-16',
            cell: (row) => row.edad ?? '—',
        },
        {
            key: 'motivo',
            header: 'Motivo',
            cell: (row) => (
                <span className="line-clamp-1 max-w-xs text-sm text-muted-foreground">
                    {row.motivo_retencion || '—'}
                </span>
            ),
        },
        {
            key: 'denuncia',
            header: 'Denuncia',
            cell: (row) => row.denuncia_label || '—',
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
            <Head title="Detenidos" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <Fingerprint className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">
                                    Detenidos
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Registro de personas detenidas.
                                </p>
                            </div>
                        </div>
                        <Button onClick={abrirCrear}>
                            <Plus className="mr-2 size-4" /> Nuevo
                        </Button>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={detenidos}
                    server={paginacion}
                    searchPlaceholder="Buscar por nombre, alias o motivo..."
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
                title={formMode === 'edit' ? 'Editar detenido' : 'Nuevo detenido'}
                description="Datos de la persona detenida."
                submitLabel={formMode === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={enviar}
            >
                <AsyncSearchPicker
                    label="Denuncia vinculada"
                    searchUrl="/seguridad/denuncias/buscar"
                    value={form.data.denuncia_id as number | null}
                    valueLabel={editando?.denuncia_label}
                    error={form.errors.denuncia_id}
                    onChange={(id) => form.setData('denuncia_id', id)}
                    placeholder="Buscar denuncia..."
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('nombre', 'Nombre(s)')}
                    {texto('paterno', 'Apellido paterno')}
                    {texto('materno', 'Apellido materno')}
                    {texto('alias', 'Alias')}
                    {texto('edad', 'Edad', 'number')}
                    {texto('fecha_nac', 'Nacimiento', 'date')}
                    <SelectField
                        label="Sexo"
                        value={form.data.sexo as number | null}
                        options={SEXOS}
                        error={form.errors.sexo}
                        onChange={(v) => form.setData('sexo', v)}
                    />
                    {texto('nacionalidad', 'Nacionalidad')}
                    {texto('lugar_nac', 'Lugar de nacimiento')}
                    <SelectField
                        label="Ocupación"
                        value={form.data.ocupacion_id as number | null}
                        options={opciones.ocupaciones}
                        error={form.errors.ocupacion_id}
                        onChange={(v) => form.setData('ocupacion_id', v)}
                    />
                    <SelectField
                        label="Estado civil"
                        value={form.data.estado_civil_id as number | null}
                        options={opciones.estadosCiviles}
                        error={form.errors.estado_civil_id}
                        onChange={(v) => form.setData('estado_civil_id', v)}
                    />
                </div>

                <div className="space-y-2 border-t pt-4">
                    <p className="text-sm font-semibold text-muted-foreground">
                        Domicilio y contacto
                    </p>
                    <UbicacionSelects
                        estados={opciones.estados}
                        value={{
                            estado_id: form.data.estado_id as number | null,
                            municipio_id: form.data.municipio_id as number | null,
                            localidad_id: null,
                        }}
                        onChange={(patch) =>
                            form.setData({
                                ...form.data,
                                estado_id: patch.estado_id ?? form.data.estado_id,
                                municipio_id:
                                    patch.municipio_id ?? form.data.municipio_id,
                            })
                        }
                    />
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('direccion', 'Dirección')}
                        {texto('celular', 'Celular')}
                        {texto('telefono', 'Teléfono')}
                    </div>
                </div>

                <div className="space-y-3 border-t pt-4">
                    <p className="text-sm font-semibold text-muted-foreground">
                        Retención
                    </p>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('lugar_retencion', 'Lugar de retención')}
                        {texto('fecha_retencion', 'Fecha de retención')}
                        {texto('padre_nombre', 'Nombre del padre')}
                        {texto('madre_nombre', 'Nombre de la madre')}
                    </div>
                    <Field>
                        <Label htmlFor="det-motivo">Motivo de retención</Label>
                        <textarea
                            id="det-motivo"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={(form.data.motivo_retencion as string) ?? ''}
                            onChange={(e) =>
                                form.setData('motivo_retencion', e.target.value)
                            }
                        />
                    </Field>
                    <Field>
                        <Label htmlFor="det-obs">Observaciones</Label>
                        <textarea
                            id="det-obs"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={(form.data.observaciones as string) ?? ''}
                            onChange={(e) =>
                                form.setData('observaciones', e.target.value)
                            }
                        />
                    </Field>
                </div>
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar detenido"
                entityLabel="a"
                itemName={eliminar?.nombre_completo}
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

DetenidosIndex.layout = {
    breadcrumbs: [
        { title: 'Seguridad', href: index() },
        { title: 'Detenidos', href: index() },
    ],
};
