import type {
    DateSelectArg,
    EventClickArg,
    EventContentArg,
    EventDropArg,
    EventInput,
    EventSourceFuncArg,
} from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin, { Draggable } from '@fullcalendar/interaction';
import type { EventResizeDoneArg } from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, GripVertical, ListChecks, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';

type Opcion = { id: number; nombre: string };
type Tipo = { id: number; nombre: string; color: string | null };

type EventoForm = {
    titulo: string;
    tipo_evento_id: number | null;
    inicio: string;
    fin: string;
    todo_el_dia: boolean;
    lugar: string;
    estado_id: number | null;
    municipio_id: number | null;
    localidad_id: number | null;
    descripcion: string;
    contacto: string;
    telefono: string;
    representante: string;
    personas: string;
    recomendaciones: string;
    /** 1 = Presidente (a) · 2 = Representante. */
    asiste: number | null;
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
    estado_id: null,
    municipio_id: null,
    localidad_id: null,
    descripcion: '',
    contacto: '',
    telefono: '',
    representante: '',
    personas: '',
    recomendaciones: '',
    asiste: null,
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

/** Cambio de fecha/duración pendiente de confirmar. */
type Movimiento = {
    tipo: 'fecha' | 'duracion';
    arg: EventDropArg | EventResizeDoneArg;
};

export default function AgendaIndex({
    tipos,
    estados,
    opcionesAsiste,
}: {
    tipos: Tipo[];
    estados: Opcion[];
    opcionesAsiste: Opcion[];
}) {
    const calendarRef = useRef<FullCalendar>(null);
    const rail = useRef<HTMLDivElement>(null);
    const [dialogo, setDialogo] = useState<'create' | 'edit' | null>(null);
    const [editId, setEditId] = useState<number | null>(null);
    const [localidadNombre, setLocalidadNombre] = useState<string | null>(null);
    const [eliminar, setEliminar] = useState<{ id: number; titulo: string } | null>(
        null,
    );
    const [movimiento, setMovimiento] = useState<Movimiento | null>(null);
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

    // Los tipos del panel se arrastran al calendario para crear, como en el
    // legacy ("Arrastre para crear un evento").
    useEffect(() => {
        if (!rail.current) {
            return;
        }

        const draggable = new Draggable(rail.current, {
            itemSelector: '[data-tipo]',
            eventData: (el) => ({
                title: el.getAttribute('data-nombre') ?? '',
                create: false,
            }),
        });

        return () => draggable.destroy();
    }, []);

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

    const abrirCrear = (
        inicio: string,
        fin: string,
        todoDia = false,
        tipoEventoId: number | null = null,
    ) => {
        setEditId(null);
        setLocalidadNombre(null);
        form.clearErrors();
        form.setData({
            ...VACIO,
            inicio: aLocal(inicio),
            fin: aLocal(fin),
            todo_el_dia: todoDia,
            tipo_evento_id: tipoEventoId,
        });
        setDialogo('create');
    };

    const abrirEditar = (arg: EventClickArg) => {
        const e = arg.event;
        const props = e.extendedProps as Record<string, unknown>;
        setEditId(Number(e.id));
        setLocalidadNombre((props.localidad_nombre as string) ?? null);
        form.clearErrors();
        form.setData({
            titulo: e.title,
            tipo_evento_id: (props.tipo_evento_id as number | null) ?? null,
            inicio: aLocal(e.start?.toISOString() ?? null),
            fin: aLocal(e.end?.toISOString() ?? null),
            todo_el_dia: e.allDay,
            lugar: (props.lugar as string) ?? '',
            estado_id: (props.estado_id as number | null) ?? null,
            municipio_id: (props.municipio_id as number | null) ?? null,
            localidad_id: (props.localidad_id as number | null) ?? null,
            descripcion: (props.descripcion as string) ?? '',
            contacto: (props.contacto as string) ?? '',
            telefono: (props.telefono as string) ?? '',
            representante: (props.representante as string) ?? '',
            personas: (props.personas as string) ?? '',
            recomendaciones: (props.recomendaciones as string) ?? '',
            asiste: (props.asiste as number | null) ?? null,
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

    // El legacy pregunta antes de guardar un arrastre o un cambio de duración.
    const confirmarMovimiento = () => {
        if (!movimiento) {
            return;
        }

        const e = movimiento.arg.event;
        router.put(
            `/agenda/eventos/${e.id}`,
            {
                titulo: e.title,
                tipo_evento_id:
                    (e.extendedProps.tipo_evento_id as number | null) ?? null,
                // Hora de pared, igual que en el feed y en el formulario.
                inicio: aLocal(e.start?.toISOString() ?? null),
                fin: e.end ? aLocal(e.end.toISOString()) : null,
                todo_el_dia: e.allDay,
            },
            {
                preserveScroll: true,
                onSuccess: () => setMovimiento(null),
                onError: () => {
                    toast.error('No se pudo mover el evento.');
                    movimiento.arg.revert();
                    setMovimiento(null);
                },
            },
        );
    };

    const cancelarMovimiento = () => {
        movimiento?.arg.revert();
        setMovimiento(null);
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
                            Arrastra un tipo al calendario, o selecciona un
                            horario para crear un evento.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[13rem_1fr]">
                    {/* Panel de tipos arrastrables (legacy: "Agregar nuevo evento"). */}
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                        <p className="text-sm font-semibold">Agregar evento</p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Arrastra un tipo al calendario.
                        </p>

                        <div ref={rail} className="mt-3 space-y-2">
                            {tipos.map((tipo) => (
                                <div
                                    key={tipo.id}
                                    data-tipo={tipo.id}
                                    data-nombre={tipo.nombre}
                                    className="flex cursor-grab items-center gap-2 rounded-md border px-2.5 py-2 text-xs font-medium active:cursor-grabbing"
                                    style={{
                                        borderLeft: `3px solid ${tipo.color ?? 'var(--color-primary)'}`,
                                    }}
                                >
                                    <GripVertical className="size-3.5 shrink-0 text-muted-foreground" />
                                    <span className="truncate">
                                        {tipo.nombre}
                                    </span>
                                </div>
                            ))}
                            {tipos.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    No hay tipos de evento. Créalos en
                                    Catálogos.
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="agenda-calendario rounded-xl border border-sidebar-border/70 bg-card p-4">
                        <FullCalendar
                            ref={calendarRef}
                            plugins={[
                                dayGridPlugin,
                                timeGridPlugin,
                                interactionPlugin,
                            ]}
                            initialView="timeGridWeek"
                            locale={esLocale}
                            headerToolbar={{
                                left: 'prev,next today',
                                center: 'title',
                                right: 'dayGridMonth,timeGridWeek,timeGridDay',
                            }}
                            height="auto"
                            // El legacy arranca a las 6:00 y da 1 h por defecto.
                            slotMinTime="06:00:00"
                            defaultTimedEventDuration="01:00:00"
                            dayMaxEvents
                            nowIndicator
                            // Marca la jornada para que se vea de un vistazo
                            // qué está dentro y qué fuera del horario.
                            businessHours={{
                                daysOfWeek: [1, 2, 3, 4, 5],
                                startTime: '08:00',
                                endTime: '18:00',
                            }}
                            allDayText="Todo el día"
                            // En semana y día el encabezado lleva la fecha; en
                            // mes las columnas son días de la semana a secas.
                            dayHeaderFormat={{ weekday: 'short', day: 'numeric' }}
                            views={{
                                dayGridMonth: {
                                    dayHeaderFormat: { weekday: 'short' },
                                },
                            }}
                            selectable
                            editable
                            droppable
                            events={cargarEventos}
                            select={(arg: DateSelectArg) =>
                                abrirCrear(arg.startStr, arg.endStr, arg.allDay)
                            }
                            eventClick={abrirEditar}
                            eventDrop={(arg) =>
                                setMovimiento({ tipo: 'fecha', arg })
                            }
                            eventResize={(arg) =>
                                setMovimiento({ tipo: 'duracion', arg })
                            }
                            // Al soltar un tipo se abre el alta con el tipo y
                            // el horario ya puestos.
                            drop={(arg) => {
                                const id = arg.draggedEl.getAttribute('data-tipo');
                                abrirCrear(
                                    arg.date.toISOString(),
                                    '',
                                    arg.allDay,
                                    id ? Number(id) : null,
                                );
                            }}
                            eventReceive={(arg) => arg.event.remove()}
                            eventContent={contenidoEvento}
                        />
                    </div>
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
                size="xl"
                title={dialogo === 'edit' ? 'Editar evento' : 'Nuevo evento'}
                description="Captura los datos del evento."
                submitLabel={dialogo === 'edit' ? 'Guardar cambios' : 'Guardar'}
                processing={form.processing}
                onSubmit={enviar}
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    <FormInputField
                        id="ev-titulo"
                        label="Título del evento"
                        value={form.data.titulo}
                        error={form.errors.titulo}
                        onChange={(e) => form.setData('titulo', e.target.value)}
                    />
                    <SelectField
                        label="Tipo"
                        value={form.data.tipo_evento_id}
                        options={tipos}
                        error={form.errors.tipo_evento_id}
                        onChange={(v) => form.setData('tipo_evento_id', v)}
                    />
                    <FormInputField
                        id="ev-lugar"
                        label="Lugar del evento"
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
                    <FormInputField
                        id="ev-personas"
                        label="Número de personas"
                        value={form.data.personas}
                        error={form.errors.personas}
                        onChange={(e) =>
                            form.setData('personas', e.target.value)
                        }
                    />
                </div>

                <div className="space-y-2">
                    <p className="text-sm font-medium text-muted-foreground">
                        Localidad del evento
                    </p>
                    <UbicacionSelects
                        estados={estados}
                        value={{
                            estado_id: form.data.estado_id,
                            municipio_id: form.data.municipio_id,
                            localidad_id: form.data.localidad_id,
                        }}
                        localidadNombre={localidadNombre}
                        errors={{ localidad_id: form.errors.localidad_id }}
                        onChange={(patch) =>
                            form.setData({ ...form.data, ...patch })
                        }
                    />
                </div>

                <Field>
                    <Label htmlFor="ev-desc">Descripción del evento</Label>
                    <textarea
                        id="ev-desc"
                        className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={form.data.descripcion}
                        onChange={(e) =>
                            form.setData('descripcion', e.target.value)
                        }
                    />
                </Field>

                <Field>
                    <Label htmlFor="ev-recom">
                        Recomendaciones / Instrucciones
                    </Label>
                    <textarea
                        id="ev-recom"
                        className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        placeholder="Vestimenta, acompañamiento, obsequios, instrucciones a los funcionarios…"
                        value={form.data.recomendaciones}
                        onChange={(e) =>
                            form.setData('recomendaciones', e.target.value)
                        }
                    />
                </Field>

                <div className="grid gap-4 sm:grid-cols-3">
                    <FormInputField
                        id="ev-contacto"
                        label="Contacto del evento"
                        value={form.data.contacto}
                        error={form.errors.contacto}
                        onChange={(e) =>
                            form.setData('contacto', e.target.value)
                        }
                    />
                    <FormInputField
                        id="ev-telefono"
                        label="Teléfono de contacto"
                        value={form.data.telefono}
                        error={form.errors.telefono}
                        onChange={(e) =>
                            form.setData('telefono', e.target.value)
                        }
                    />
                    <SelectField
                        label="¿Quién asiste?"
                        emptyLabel="Sin definir"
                        value={form.data.asiste}
                        options={opcionesAsiste}
                        error={form.errors.asiste}
                        onChange={(v) =>
                            form.setData({
                                ...form.data,
                                asiste: v,
                                // El nombre sólo aplica si va el representante.
                                representante:
                                    v === 2 ? form.data.representante : '',
                            })
                        }
                    />
                </div>

                {form.data.asiste === 2 && (
                    <FormInputField
                        id="ev-representante"
                        label="Representante del presidente"
                        value={form.data.representante}
                        error={form.errors.representante}
                        onChange={(e) =>
                            form.setData('representante', e.target.value)
                        }
                    />
                )}

                <div className="flex flex-wrap gap-4">
                    {(
                        [
                            ['todo_el_dia', 'Evento de todo el día'],
                            ['confirmado', 'Confirmado'],
                            ['discurso', 'Lleva discurso'],
                            ['privado', 'Evento privado'],
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

            {/* Confirmación al arrastrar o redimensionar, como el legacy. */}
            <Dialog
                open={movimiento !== null}
                onOpenChange={(o) => !o && cancelarMovimiento()}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {movimiento?.tipo === 'duracion'
                                ? 'Cambiar la duración'
                                : 'Cambiar la fecha'}
                        </DialogTitle>
                        <DialogDescription>
                            {movimiento?.tipo === 'duracion'
                                ? '¿Guardar la nueva duración del evento?'
                                : '¿Guardar la nueva fecha y hora del evento?'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={cancelarMovimiento}>
                            Cancelar
                        </Button>
                        <Button onClick={confirmarMovimiento}>Guardar</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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

/**
 * Etiqueta del evento en el calendario, como el legacy: el prefijo PRIV para
 * los privados y el lugar en un segundo renglón.
 */
function contenidoEvento(arg: EventContentArg) {
    const props = arg.event.extendedProps as Record<string, unknown>;
    const lugar = (props.lugar as string) ?? '';

    return (
        <div className="overflow-hidden px-1 leading-tight">
            <div className="truncate text-[11px] font-medium">
                {props.privado ? 'PRIV · ' : ''}
                {arg.timeText && (
                    <span className="tabular-nums opacity-80">
                        {arg.timeText}{' '}
                    </span>
                )}
                {arg.event.title}
            </div>
            <div className="truncate text-[10px] opacity-80">
                LUGAR: {lugar || 'S/D'}
            </div>
        </div>
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
