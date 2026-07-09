import { Head, router, useForm, usePage } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Plus, Trash2, Truck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { DataTable } from '@/components/data-table';
import type { DataTableColumn, DataTableServer } from '@/components/data-table';
import { FormInputField } from '@/components/form-input-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, index, store } from '@/routes/directorio/proveedores';

type Opcion = { id: number; nombre: string };
type Proveedor = {
    id: number;
    nombre: string;
    rfc: string | null;
    especialidad: string | null;
    municipio_nombre: string | null;
    localidad_nombre: string | null;
    [key: string]: string | number | null;
};
type Form = Record<string, string | number | null>;

const CAMPOS_INICIALES: Form = {
    id: null,
    nombre: '',
    rfc: '',
    rep_legal: '',
    especialidad: '',
    calificacion: '',
    num_prov_gob: '',
    calle: '',
    num_ext: '',
    num_int: '',
    colonia: '',
    cp: '',
    estado_id: null,
    municipio_id: null,
    localidad_id: null,
    telefono: '',
    celular: '',
    correo: '',
};

export default function ProveedoresIndex({
    proveedores,
    paginacion,
    opciones,
}: {
    proveedores: Proveedor[];
    paginacion: DataTableServer;
    opciones: { estados: Opcion[] };
}) {
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [editando, setEditando] = useState<Proveedor | null>(null);
    const [eliminar, setEliminar] = useState<Proveedor | null>(null);
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

    const abrirEditar = (p: Proveedor) => {
        setEditando(p);
        form.clearErrors();
        const datos: Form = { ...CAMPOS_INICIALES };

        for (const clave of Object.keys(CAMPOS_INICIALES)) {
            datos[clave] = p[clave] ?? (CAMPOS_INICIALES[clave] ?? '');
        }

        datos.id = p.id;
        form.setData(datos);
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
            id={`prov-${clave}`}
            label={label}
            value={(form.data[clave] as string) ?? ''}
            error={form.errors[clave]}
            onChange={(e) => form.setData(clave, e.target.value)}
        />
    );

    const columns: DataTableColumn<Proveedor>[] = [
        {
            key: 'nombre',
            header: 'Proveedor',
            accessor: (row) => row.nombre,
            cell: (row) => <span className="font-medium">{row.nombre}</span>,
        },
        {
            key: 'rfc',
            header: 'RFC',
            cell: (row) => row.rfc || '—',
        },
        {
            key: 'especialidad',
            header: 'Especialidad',
            cell: (row) => row.especialidad || '—',
        },
        {
            key: 'municipio',
            header: 'Municipio',
            cell: (row) => row.municipio_nombre || '—',
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
            <Head title="Proveedores" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <Truck className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">
                                    Proveedores
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Catálogo de proveedores.
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
                    data={proveedores}
                    server={paginacion}
                    searchPlaceholder="Buscar por nombre, RFC o especialidad..."
                />
            </div>

            <CrudFormDialog
                open={formMode !== null}
                onOpenChange={cerrar}
                size="lg"
                title={
                    formMode === 'edit' ? 'Editar proveedor' : 'Nuevo proveedor'
                }
                description="Captura los datos del proveedor."
                submitLabel={formMode === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={enviar}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {texto('nombre', 'Nombre / Razón social')}
                    {texto('rfc', 'RFC')}
                    {texto('rep_legal', 'Representante legal')}
                    {texto('especialidad', 'Especialidad')}
                    {texto('calificacion', 'Calificación (0-10)')}
                    {texto('num_prov_gob', 'Núm. proveedor gobierno')}
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('calle', 'Calle')}
                    {texto('num_ext', 'Núm. exterior')}
                    {texto('num_int', 'Núm. interior')}
                    {texto('colonia', 'Colonia')}
                    {texto('cp', 'C.P.')}
                </div>

                <UbicacionSelects
                    estados={opciones.estados}
                    value={{
                        estado_id: form.data.estado_id as number | null,
                        municipio_id: form.data.municipio_id as number | null,
                        localidad_id: form.data.localidad_id as number | null,
                    }}
                    municipioNombre={editando?.municipio_nombre}
                    localidadNombre={editando?.localidad_nombre}
                    errors={{
                        estado_id: form.errors.estado_id,
                        municipio_id: form.errors.municipio_id,
                        localidad_id: form.errors.localidad_id,
                    }}
                    onChange={(patch) => form.setData({ ...form.data, ...patch })}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('telefono', 'Teléfono')}
                    {texto('celular', 'Celular')}
                    {texto('correo', 'Correo')}
                </div>
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar proveedor"
                entityLabel="al proveedor"
                itemName={eliminar?.nombre}
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

ProveedoresIndex.layout = {
    breadcrumbs: [
        { title: 'Directorio', href: index() },
        { title: 'Proveedores', href: index() },
    ],
};
