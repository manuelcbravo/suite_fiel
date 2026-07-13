import type {
    DateSelectArg,
    EventClickArg,
    EventDropArg,
    EventInput,
    EventSourceFuncArg,
} from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import type {EventResizeDoneArg} from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, ListChecks, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';

type Tipo = { id: number; nombre: string; color: string | null };
type EventoForm = {
    titulo: string;
    tipo_evento_id: number | null;
    inicio: string;
    fin: string;
    todo_el_dia: boolean;
    lugar: string;
    descripcion: string;
    contacto: string;
    telefono: string;
    representante: string;
    personas: string;
    recomendaciones: string;
    asiste: boolean;
    confirmado: boolean;
    discurso: boolean;
    privado: boolean;
};

const VACIO: EventoForm = {
    titulo: '',
    tipo_evento_id: null,
    inicio: '',
    fin: '',
    todo_el_dia: false,
    lugar: '',
    descripcion: '',
    contacto: '',
    telefono: '',
    representante: '',
    personas: '',
    recomendaciones: '',
    asiste: false,
    confirmado: false,
    discurso: false,
    privado: false,
};

// ISO -> valor para <input type="datetime-local"> (YYYY-MM-DDTHH:MM local).
function aLocal(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function AgendaIndex({ tipos }: { tipos: Tipo[] }) {
    const calendarRef = useRef<FullCalendar>(null);
    const [dialogo, setDialogo] = useState<'create' | 'edit' | null>(null);
    const [editId, setEditId] = useState<number | null>(null);
    const [eliminar, setEliminar] = useState<{ id: number; titulo: string } | null>(
        null,
    );
    const form = useForm<EventoForm>(VACIO);
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const recargar = () => calendarRef.current?.getApi().refetchEvents();

    const cargarEventos = (
        info: EventSourceFuncArg,
        success: (eventos: EventInput[]) => void,
        failure: (error: Error) => void,
    ) => {
        fetch(`/agenda/eventos?start=${info.startStr}&end=${info.endStr}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('feed'))))
            .then(success)
            .catch(failure);
    };

    const abrirCrear = (inicio: string, fin: string, todoDia = false) => {
        setEditId(null);
        form.clearErrors();
        form.setData({
            ...VACIO,
            inicio: aLocal(inicio),
            fin: aLocal(fin),
            todo_el_dia: todoDia,
        });
        setDialogo('create');
    };

    const abrirEditar = (arg: EventClickArg) => {
        const e = arg.event;
        const props = e.extendedProps as Record<string, unknown>;
        setEditId(Number(e.id));
        form.clearErrors();
        form.setData({
            titulo: e.title,
            tipo_evento_id: (props.tipo_evento_id as number | null) ?? null,
            inicio: aLocal(e.start?.toISOString() ?? null),
            fin: aLocal(e.end?.toISOString() ?? null),
            todo_el_dia: e.allDay,
            lugar: (props.lugar as string) ?? '',
            descripcion: (props.descripcion as string) ?? '',
            contacto: (props.contacto as string) ?? '',
            telefono: (props.telefono as string) ?? '',
            representante: (props.representante as string) ?? '',
            personas: (props.personas as string) ?? '',
            recomendaciones: (props.recomendaciones as string) ?? '',
            asiste: Boolean(props.asiste),
            confirmado: Boolean(props.confirmado),
            discurso: Boolean(props.discurso),
            privado: Boolean(props.privado),
        });
        setDialogo('edit');
    };

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogo(null);
                setEditId(null);
                form.reset();
                recargar();
            },
            onError: (errores: Record<string, string>) =>
                toast.error(
                    resolveFormErrorMessage(errores, 'Revisa los campos.'),
                ),
        };

        if (editId) {
            form.put(`/agenda/eventos/${editId}`, opciones);
        } else {
            form.post('/agenda/eventos', opciones);
        }
    };

    // Arrastrar/redimensionar: persiste solo las nuevas fechas.
    const moverEvento = (arg: EventDropArg | EventResizeDoneArg) => {
        const e = arg.event;
        router.put(
            `/agenda/eventos/${e.id}`,
            {
                titulo: e.title,
                tipo_evento_id:
                    (e.extendedProps.tipo_evento_id as number | null) ?? null,
                inicio: e.start?.toISOString() ?? '',
                fin: e.end?.toISOString() ?? null,
                todo_el_dia: e.allDay,
            },
            {
                preserveScroll: true,
                onError: () => {
                    toast.error('No se pudo mover el evento.');
                    arg.revert();
                },
            },
        );
    };

    return (
        <>
            <Head title="Agenda" />
            <div className="space-y-4 p-4">
                <div className="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <CalendarDays className="size-5 text-primary" />
                    <div>
                        <h1 className="text-xl font-semibold">Agenda</h1>
                        <p className="text-sm text-muted-foreground">
                            Calendario de eventos. Haz clic en un día para crear.
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <FullCalendar
                        ref={calendarRef}
                        plugins={[
                            dayGridPlugin,
                            timeGridPlugin,
                            interactionPlugin,
                        ]}
                        initialView="dayGridMonth"
                        locale={esLocale}
                        headerToolbar={{
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay',
                        }}
                        height="auto"
                        selectable
                        editable
                        events={cargarEventos}
                        select={(arg: DateSelectArg) =>
                            abrirCrear(arg.startStr, arg.endStr, arg.allDay)
                        }
                        eventClick={abrirEditar}
                        eventDrop={moverEvento}
                        eventResize={moverEvento}
                    />
                </div>
            </div>

            <CrudFormDialog
                open={dialogo !== null}
                onOpenChange={(o) => {
                    if (!o) {
                        setDialogo(null);
                        setEditId(null);
                        form.clearErrors();
                    }
                }}
                size="lg"
                title={dialogo === 'edit' ? 'Editar evento' : 'Nuevo evento'}
                description="Captura los datos del evento."
                submitLabel={dialogo === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={enviar}
            >
                <FormInputField
                    id="ev-titulo"
                    label="Título"
                    value={form.data.titulo}
                    error={form.errors.titulo}
                    onChange={(e) => form.setData('titulo', e.target.value)}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <SelectField
                        label="Tipo"
                        value={form.data.tipo_evento_id}
                        options={tipos}
                        error={form.errors.tipo_evento_id}
                        onChange={(v) => form.setData('tipo_evento_id', v)}
                    />
                    <FormInputField
                        id="ev-lugar"
                        label="Lugar"
                        value={form.data.lugar}
                        error={form.errors.lugar}
                        onChange={(e) => form.setData('lugar', e.target.value)}
                    />
                    <FormInputField
                        id="ev-inicio"
                        type="datetime-local"
                        label="Inicio"
                        value={form.data.inicio}
                        error={form.errors.inicio}
                        onChange={(e) => form.setData('inicio', e.target.value)}
                    />
                    <FormInputField
                        id="ev-fin"
                        type="datetime-local"
                        label="Fin"
                        value={form.data.fin}
                        error={form.errors.fin}
                        onChange={(e) => form.setData('fin', e.target.value)}
                    />
                </div>

                <Field>
                    <Label htmlFor="ev-desc">Descripción</Label>
                    <textarea
                        id="ev-desc"
                        className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={form.data.descripcion}
                        onChange={(e) =>
                            form.setData('descripcion', e.target.value)
                        }
                    />
                </Field>

                <div className="grid gap-4 sm:grid-cols-3">
                    <FormInputField
                        id="ev-contacto"
                        label="Contacto"
                        value={form.data.contacto}
                        error={form.errors.contacto}
                        onChange={(e) =>
                            form.setData('contacto', e.target.value)
                        }
                    />
                    <FormInputField
                        id="ev-telefono"
                        label="Teléfono"
                        value={form.data.telefono}
                        error={form.errors.telefono}
                        onChange={(e) =>
                            form.setData('telefono', e.target.value)
                        }
                    />
                    <FormInputField
                        id="ev-representante"
                        label="Representante"
                        value={form.data.representante}
                        error={form.errors.representante}
                        onChange={(e) =>
                            form.setData('representante', e.target.value)
                        }
                    />
                </div>

                <div className="flex flex-wrap gap-4">
                    {(
                        [
                            ['todo_el_dia', 'Todo el día'],
                            ['asiste', 'Asiste'],
                            ['confirmado', 'Confirmado'],
                            ['discurso', 'Discurso'],
                            ['privado', 'Privado'],
                        ] as const
                    ).map(([clave, label]) => (
                        <label
                            key={clave}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={form.data[clave]}
                                onCheckedChange={(v) =>
                                    form.setData(clave, Boolean(v))
                                }
                            />
                            {label}
                        </label>
                    ))}
                </div>

                {dialogo === 'edit' && editId && (
                    <>
                        <NotasEvento eventoId={editId} />
                        <button
                            type="button"
                            className="text-sm text-destructive hover:underline"
                            onClick={() => {
                                setEliminar({
                                    id: editId,
                                    titulo: form.data.titulo,
                                });
                                setDialogo(null);
                            }}
                        >
                            Eliminar evento
                        </button>
                    </>
                )}
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar evento"
                entityLabel="el evento"
                itemName={eliminar?.titulo}
                onConfirm={() => {
                    if (!eliminar) {
                        return;
                    }

                    router.delete(`/agenda/eventos/${eliminar.id}`, {
                        preserveScroll: true,
                        onSuccess: () => {
                            setEliminar(null);
                            recargar();
                        },
                        onError: () => toast.error('No se pudo eliminar.'),
                    });
                }}
            />
        </>
    );
}

type Pendiente = { id: number; texto: string; realizado: boolean };

/**
 * Checklist de notas del evento (nota única auto-creada por evento). Gestiona su
 * propio estado y persiste vía Inertia sin cerrar el diálogo.
 */
function NotasEvento({ eventoId }: { eventoId: number }) {
    const [pendientes, setPendientes] = useState<Pendiente[]>([]);
    const [texto, setTexto] = useState('');
    const [cargando, setCargando] = useState(true);

    const cargar = useCallback(() => {
        fetch(`/agenda/eventos/${eventoId}/notas`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('notas'))))
            .then((d: { pendientes: Pendiente[] }) => {
                setPendientes(d.pendientes);
                setCargando(false);
            })
            .catch(() => setCargando(false));
    }, [eventoId]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const opciones = { preserveScroll: true, preserveState: true } as const;

    const agregar = () => {
        const valor = texto.trim();

        if (!valor) {
            return;
        }

        router.post(
            `/agenda/eventos/${eventoId}/notas`,
            { texto: valor },
            {
                ...opciones,
                onSuccess: () => {
                    setTexto('');
                    cargar();
                },
                onError: () => toast.error('No se pudo agregar la nota.'),
            },
        );
    };

    const alternar = (id: number) =>
        router.put(`/agenda/eventos/notas/${id}/toggle`, {}, { ...opciones, onSuccess: cargar });

    const eliminar = (id: number) =>
        router.delete(`/agenda/eventos/notas/${id}`, { ...opciones, onSuccess: cargar });

    return (
        <div className="space-y-2 rounded-md border p-3">
            <p className="flex items-center gap-2 text-sm font-semibold">
                <ListChecks className="size-4 text-primary" /> Notas del evento
            </p>

            <div className="flex gap-2">
                <Input
                    value={texto}
                    placeholder="Escribe una anotación o recordatorio..."
                    onChange={(e) => setTexto(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            agregar();
                        }
                    }}
                />
                <Button type="button" size="icon" onClick={agregar}>
                    <Plus className="size-4" />
                </Button>
            </div>

            {cargando ? (
                <p className="text-sm text-muted-foreground">Cargando...</p>
            ) : pendientes.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Sin anotaciones para este evento.
                </p>
            ) : (
                <ul className="space-y-1">
                    {pendientes.map((p) => (
                        <li
                            key={p.id}
                            className="flex items-center gap-2 rounded-md px-1 py-1 text-sm"
                        >
                            <Checkbox
                                checked={p.realizado}
                                onCheckedChange={() => alternar(p.id)}
                            />
                            <span
                                className={
                                    p.realizado
                                        ? 'flex-1 text-muted-foreground line-through'
                                        : 'flex-1'
                                }
                            >
                                {p.texto}
                            </span>
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-destructive"
                                onClick={() => eliminar(p.id)}
                                aria-label="Eliminar anotación"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

AgendaIndex.layout = {
    breadcrumbs: [{ title: 'Agenda', href: '/agenda' }],
};
