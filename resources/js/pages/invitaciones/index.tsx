import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Mail, MailPlus, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AsyncSearchPicker } from '@/components/async-search-picker';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { DataTable } from '@/components/data-table';
import type { DataTableColumn, DataTableServer } from '@/components/data-table';
import { FormInputField } from '@/components/form-input-field';
import { SelectField } from '@/components/select-field';
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
import { formatDate } from '@/lib/date';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, index, store } from '@/routes/invitaciones';

type Opcion = { id: number; nombre: string };
type Invitacion = {
    id: number;
    titulo: string | null;
    destinatario: string | null;
    inicio: string | null;
    fin: string | null;
    todo_el_dia: boolean;
    tipo_evento_id: number | null;
    tipo_nombre: string | null;
    evento_id: number | null;
    evento_titulo: string | null;
    lugar: string | null;
    descripcion: string | null;
    recomendaciones: string | null;
    contacto: string | null;
    telefono: string | null;
    fecha_recepcion: string | null;
    confirmado: boolean;
    atendida: boolean;
    comentario: string | null;
    correos_count: number;
};
type Correo = {
    id: number;
    correos: string;
    mensaje: string | null;
    enviado_en: string | null;
};
type Form = Record<string, string | number | boolean | null>;

function aLocal(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const VACIO: Form = {
    id: null,
    titulo: '',
    destinatario: '',
    inicio: '',
    fin: '',
    todo_el_dia: false,
    tipo_evento_id: null,
    evento_id: null,
    lugar: '',
    descripcion: '',
    recomendaciones: '',
    contacto: '',
    telefono: '',
    fecha_recepcion: '',
    confirmado: false,
    atendida: false,
    comentario: '',
};

export default function InvitacionesIndex({
    invitaciones,
    paginacion,
    opciones,
}: {
    invitaciones: Invitacion[];
    paginacion: DataTableServer;
    opciones: { tipos: Opcion[] };
}) {
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [editando, setEditando] = useState<Invitacion | null>(null);
    const [eliminar, setEliminar] = useState<Invitacion | null>(null);
    const [detalle, setDetalle] = useState<Invitacion | null>(null);
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

    const abrirEditar = (i: Invitacion) => {
        setEditando(i);
        form.clearErrors();
        form.setData({
            id: i.id,
            titulo: i.titulo ?? '',
            destinatario: i.destinatario ?? '',
            inicio: aLocal(i.inicio),
            fin: aLocal(i.fin),
            todo_el_dia: i.todo_el_dia,
            tipo_evento_id: i.tipo_evento_id,
            evento_id: i.evento_id,
            lugar: i.lugar ?? '',
            descripcion: i.descripcion ?? '',
            recomendaciones: i.recomendaciones ?? '',
            contacto: i.contacto ?? '',
            telefono: i.telefono ?? '',
            fecha_recepcion: aLocal(i.fecha_recepcion),
            confirmado: i.confirmado,
            atendida: i.atendida,
            comentario: i.comentario ?? '',
        });
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
            id={`inv-${clave}`}
            type={type}
            label={label}
            value={(form.data[clave] as string) ?? ''}
            error={form.errors[clave]}
            onChange={(e) => form.setData(clave, e.target.value)}
        />
    );

    const columns: DataTableColumn<Invitacion>[] = [
        {
            key: 'titulo',
            header: 'Título',
            accessor: (row) => row.titulo,
            cell: (row) => (
                <span className="font-medium">{row.titulo || '—'}</span>
            ),
        },
        {
            key: 'inicio',
            header: 'Fecha',
            cell: (row) => (row.inicio ? formatDate(row.inicio) : '—'),
        },
        {
            key: 'tipo',
            header: 'Tipo',
            cell: (row) => row.tipo_nombre || '—',
        },
        {
            key: 'estado',
            header: 'Estado',
            cell: (row) => (
                <div className="flex gap-1">
                    {row.confirmado && <Badge>Confirmada</Badge>}
                    {row.atendida && <Badge variant="outline">Atendida</Badge>}
                    {!row.confirmado && !row.atendida && (
                        <Badge variant="secondary">Pendiente</Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'correos',
            header: 'Correos',
            className: 'w-16 text-center',
            cell: (row) => row.correos_count,
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
                            <Mail className="mr-2 size-4" /> Ver / notificar
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
            <Head title="Invitaciones" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <MailPlus className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">
                                    Invitaciones
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Invitaciones recibidas y su notificación.
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
                    data={invitaciones}
                    server={paginacion}
                    searchPlaceholder="Buscar por título o destinatario..."
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
                size="lg"
                title={
                    formMode === 'edit'
                        ? 'Editar invitación'
                        : 'Nueva invitación'
                }
                description="Captura los datos de la invitación."
                submitLabel={formMode === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={enviar}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {texto('titulo', 'Título')}
                    {texto('destinatario', 'Destinatario')}
                    {texto('inicio', 'Inicio', 'datetime-local')}
                    {texto('fin', 'Fin', 'datetime-local')}
                    <SelectField
                        label="Tipo"
                        value={form.data.tipo_evento_id as number | null}
                        options={opciones.tipos}
                        error={form.errors.tipo_evento_id}
                        onChange={(v) => form.setData('tipo_evento_id', v)}
                    />
                    {texto('lugar', 'Lugar')}
                </div>

                <AsyncSearchPicker
                    label="Vincular a evento de agenda (opcional)"
                    searchUrl="/invitaciones/eventos/buscar"
                    value={form.data.evento_id as number | null}
                    valueLabel={editando?.evento_titulo}
                    error={form.errors.evento_id}
                    onChange={(id) => form.setData('evento_id', id)}
                    placeholder="Buscar evento por título..."
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    {texto('contacto', 'Contacto')}
                    {texto('telefono', 'Teléfono')}
                </div>

                <Field>
                    <Label htmlFor="inv-descripcion">Descripción</Label>
                    <textarea
                        id="inv-descripcion"
                        className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={(form.data.descripcion as string) ?? ''}
                        onChange={(e) =>
                            form.setData('descripcion', e.target.value)
                        }
                    />
                </Field>

                <div className="flex flex-wrap gap-4">
                    {(
                        [
                            ['todo_el_dia', 'Todo el día'],
                            ['confirmado', 'Confirmada'],
                            ['atendida', 'Atendida'],
                        ] as const
                    ).map(([clave, label]) => (
                        <label
                            key={clave}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={form.data[clave] as boolean}
                                onCheckedChange={(v) =>
                                    form.setData(clave, Boolean(v))
                                }
                            />
                            {label}
                        </label>
                    ))}
                </div>
            </CrudFormDialog>

            {detalle && (
                <DetalleInvitacion
                    invitacion={detalle}
                    onClose={() => setDetalle(null)}
                />
            )}

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar invitación"
                entityLabel="la invitación"
                itemName={eliminar?.titulo ?? `#${eliminar?.id}`}
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

function DetalleInvitacion({
    invitacion,
    onClose,
}: {
    invitacion: Invitacion;
    onClose: () => void;
}) {
    const [correos, setCorreos] = useState<Correo[]>([]);
    const notificar = useForm<{ correos: string; mensaje: string }>({
        correos: '',
        mensaje: '',
    });

    const cargar = () => {
        fetch(`/invitaciones/${invitacion.id}/correos`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((d) => setCorreos(d as Correo[]));
    };

    useEffect(cargar, [invitacion.id]);

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        notificar.post(`/invitaciones/${invitacion.id}/correos`, {
            preserveScroll: true,
            onSuccess: () => {
                notificar.reset();
                cargar();
            },
            onError: () => toast.error('Revisa los correos.'),
        });
    };

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="lg"
            showFooter={false}
            title={invitacion.titulo || `Invitación #${invitacion.id}`}
            description={invitacion.destinatario ?? ''}
        >
            <div className="space-y-4">
                {invitacion.evento_titulo && (
                    <p className="text-sm">
                        <span className="font-medium">Evento vinculado:</span>{' '}
                        {invitacion.evento_titulo}
                    </p>
                )}

                <div>
                    <h3 className="mb-2 text-sm font-semibold">
                        Notificaciones enviadas ({correos.length})
                    </h3>
                    <div className="space-y-2">
                        {correos.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Sin notificaciones.
                            </p>
                        )}
                        {correos.map((c) => (
                            <div
                                key={c.id}
                                className="rounded-md border p-3 text-sm"
                            >
                                <p className="font-medium">{c.correos}</p>
                                {c.mensaje && (
                                    <p className="text-muted-foreground">
                                        {c.mensaje}
                                    </p>
                                )}
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {c.enviado_en}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>

                <form className="space-y-3 border-t pt-4" onSubmit={enviar}>
                    <p className="text-sm font-semibold">Registrar notificación</p>
                    <Field>
                        <Label htmlFor="not-correos">
                            Correos (separados por coma)
                        </Label>
                        <textarea
                            id="not-correos"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={notificar.data.correos}
                            onChange={(e) =>
                                notificar.setData('correos', e.target.value)
                            }
                        />
                    </Field>
                    <Field>
                        <Label htmlFor="not-mensaje">Mensaje</Label>
                        <textarea
                            id="not-mensaje"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            value={notificar.data.mensaje}
                            onChange={(e) =>
                                notificar.setData('mensaje', e.target.value)
                            }
                        />
                    </Field>
                    <Button type="submit" disabled={notificar.processing}>
                        <MailPlus className="mr-2 size-4" /> Registrar
                    </Button>
                </form>
            </div>
        </CrudFormDialog>
    );
}

InvitacionesIndex.layout = {
    breadcrumbs: [{ title: 'Invitaciones', href: index() }],
};
