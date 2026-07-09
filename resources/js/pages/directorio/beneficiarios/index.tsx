import { Head, router, useForm, usePage } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2, UserPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
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
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import {
    destroy,
    index,
    store,
} from '@/routes/directorio/beneficiarios';

type Opcion = { id: number; nombre: string };
type Beneficiario = {
    id: number;
    nombre_completo: string;
    estado_nombre: string | null;
    municipio_nombre: string | null;
    localidad_nombre: string | null;
    sector_nombre: string | null;
    [key: string]: string | number | null;
};
type Opciones = {
    estados: Opcion[];
    sectores: Opcion[];
    ocupaciones: Opcion[];
    profesiones: Opcion[];
    estadosCiviles: Opcion[];
};
type Form = Record<string, string | number | null>;

const GENEROS: Opcion[] = [
    { id: 1, nombre: 'Masculino' },
    { id: 2, nombre: 'Femenino' },
];
const TIPOS: Opcion[] = [
    { id: 1, nombre: 'Persona física' },
    { id: 2, nombre: 'Persona moral' },
];

const CAMPOS_INICIALES: Form = {
    id: null,
    nombre: '',
    paterno: '',
    materno: '',
    alias: '',
    curp: '',
    genero: null,
    nacimiento: '',
    tipo: null,
    estado_civil_id: null,
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
    celular2: '',
    correo: '',
    correo2: '',
    facebook: '',
    twitter: '',
    empresa: '',
    puesto: '',
    tel_empresa: '',
    ocupacion_id: null,
    profesion_id: null,
    sector_id: null,
    grupo: '',
    vinculo_municipal: '',
    vinculo_estatal: '',
    vinculo_federal: '',
    asist_nombre: '',
    asist_movil: '',
    asist_correo: '',
    conyuge_nombre: '',
    conyuge_movil: '',
    conyuge_nacimiento: '',
    estatus: null,
};

export default function BeneficiariosIndex({
    beneficiarios,
    paginacion,
    opciones,
}: {
    beneficiarios: Beneficiario[];
    paginacion: DataTableServer;
    opciones: Opciones;
}) {
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [editando, setEditando] = useState<Beneficiario | null>(null);
    const [eliminar, setEliminar] = useState<Beneficiario | null>(null);
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

    const abrirEditar = (b: Beneficiario) => {
        setEditando(b);
        form.clearErrors();
        const datos: Form = { ...CAMPOS_INICIALES };

        for (const clave of Object.keys(CAMPOS_INICIALES)) {
            datos[clave] = b[clave] ?? (CAMPOS_INICIALES[clave] ?? '');
        }

        datos.id = b.id;
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

    const texto = (clave: string, label: string, type = 'text') => (
        <FormInputField
            id={`bene-${clave}`}
            label={label}
            type={type}
            value={(form.data[clave] as string) ?? ''}
            error={form.errors[clave]}
            onChange={(e) => form.setData(clave, e.target.value)}
        />
    );

    const columns: DataTableColumn<Beneficiario>[] = [
        {
            key: 'nombre_completo',
            header: 'Nombre',
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
            key: 'celular',
            header: 'Celular',
            cell: (row) => (row.celular as string) || '—',
        },
        {
            key: 'municipio',
            header: 'Municipio',
            cell: (row) => row.municipio_nombre || '—',
        },
        {
            key: 'sector',
            header: 'Sector',
            cell: (row) => row.sector_nombre || '—',
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
            <Head title="Beneficiarios" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <Users className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">
                                    Beneficiarios
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Padrón de personas del directorio.
                                </p>
                            </div>
                        </div>
                        <Button onClick={abrirCrear}>
                            <UserPlus className="mr-2 size-4" /> Nuevo
                        </Button>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    data={beneficiarios}
                    server={paginacion}
                    searchPlaceholder="Buscar por nombre, CURP o celular..."
                />
            </div>

            <CrudFormDialog
                open={formMode !== null}
                onOpenChange={cerrar}
                size="xl"
                title={
                    formMode === 'edit'
                        ? 'Editar beneficiario'
                        : 'Nuevo beneficiario'
                }
                description="Captura los datos del beneficiario."
                submitLabel={
                    formMode === 'edit' ? 'Guardar cambios' : 'Guardar'
                }
                processing={form.processing}
                onSubmit={enviar}
            >
                <Seccion titulo="Datos personales">
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('nombre', 'Nombre(s)')}
                        {texto('paterno', 'Apellido paterno')}
                        {texto('materno', 'Apellido materno')}
                        {texto('alias', 'Alias')}
                        {texto('curp', 'CURP')}
                        {texto('nacimiento', 'Nacimiento', 'date')}
                        <SelectField
                            label="Género"
                            value={form.data.genero as number | null}
                            options={GENEROS}
                            error={form.errors.genero}
                            onChange={(v) => form.setData('genero', v)}
                        />
                        <SelectField
                            label="Tipo"
                            value={form.data.tipo as number | null}
                            options={TIPOS}
                            error={form.errors.tipo}
                            onChange={(v) => form.setData('tipo', v)}
                        />
                        <SelectField
                            label="Estado civil"
                            value={form.data.estado_civil_id as number | null}
                            options={opciones.estadosCiviles}
                            error={form.errors.estado_civil_id}
                            onChange={(v) => form.setData('estado_civil_id', v)}
                        />
                    </div>
                </Seccion>

                <Seccion titulo="Domicilio">
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
                            municipio_id: form.data
                                .municipio_id as number | null,
                            localidad_id: form.data
                                .localidad_id as number | null,
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
                </Seccion>

                <Seccion titulo="Contacto">
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('telefono', 'Teléfono')}
                        {texto('celular', 'Celular')}
                        {texto('celular2', 'Celular 2')}
                        {texto('correo', 'Correo')}
                        {texto('correo2', 'Correo 2')}
                        {texto('facebook', 'Facebook')}
                        {texto('twitter', 'Twitter/X')}
                    </div>
                </Seccion>

                <Seccion titulo="Laboral y clasificación">
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('empresa', 'Empresa')}
                        {texto('puesto', 'Puesto')}
                        {texto('tel_empresa', 'Tel. empresa')}
                        <SelectField
                            label="Ocupación"
                            value={form.data.ocupacion_id as number | null}
                            options={opciones.ocupaciones}
                            error={form.errors.ocupacion_id}
                            onChange={(v) => form.setData('ocupacion_id', v)}
                        />
                        <SelectField
                            label="Profesión"
                            value={form.data.profesion_id as number | null}
                            options={opciones.profesiones}
                            error={form.errors.profesion_id}
                            onChange={(v) => form.setData('profesion_id', v)}
                        />
                        <SelectField
                            label="Sector"
                            value={form.data.sector_id as number | null}
                            options={opciones.sectores}
                            error={form.errors.sector_id}
                            onChange={(v) => form.setData('sector_id', v)}
                        />
                        {texto('grupo', 'Grupo')}
                        {texto('vinculo_municipal', 'Vínculo municipal')}
                        {texto('vinculo_estatal', 'Vínculo estatal')}
                        {texto('vinculo_federal', 'Vínculo federal')}
                    </div>
                </Seccion>

                <Seccion titulo="Asistente y cónyuge">
                    <div className="grid gap-4 sm:grid-cols-3">
                        {texto('asist_nombre', 'Asistente')}
                        {texto('asist_movil', 'Móvil asistente')}
                        {texto('asist_correo', 'Correo asistente')}
                        {texto('conyuge_nombre', 'Cónyuge')}
                        {texto('conyuge_movil', 'Móvil cónyuge')}
                        {texto('conyuge_nacimiento', 'Nacimiento cónyuge', 'date')}
                    </div>
                </Seccion>
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar beneficiario"
                entityLabel="a"
                itemName={eliminar?.nombre_completo}
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

function Seccion({
    titulo,
    children,
}: {
    titulo: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-3 border-t pt-4 first:border-t-0 first:pt-0">
            <h3 className="text-sm font-semibold text-muted-foreground">
                {titulo}
            </h3>
            {children}
        </div>
    );
}

BeneficiariosIndex.layout = {
    breadcrumbs: [
        { title: 'Directorio', href: index() },
        { title: 'Beneficiarios', href: index() },
    ],
};
