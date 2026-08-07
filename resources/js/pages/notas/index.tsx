import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    Check,
    ListPlus,
    MapPin,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AsyncSearchPicker } from '@/components/async-search-picker';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { Encabezado } from '@/components/pagina';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/date';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, index as notasIndex, store } from '@/routes/notas';
import { leerAsiento } from './asiento';

type Pendiente = { id: number; texto: string; realizado: boolean };
type Evento = {
    titulo: string;
    fecha: string | null;
    hora: string | null;
    lugar: string | null;
};
type Nota = {
    id: number;
    nota: string | null;
    fecha: string | null;
    evento_id: number | null;
    evento_titulo: string | null;
    evento: Evento | null;
    pendientes: Pendiente[];
};
type Filtros = { desde: string; hasta: string };
type Form = {
    id: number | null;
    nota: string;
    fecha: string;
    evento_id: number | null;
};

const VACIO: Form = { id: null, nota: '', fecha: '', evento_id: null };

const DIA = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

/** "27 de junio de 2017" a partir de un 'YYYY-MM-DD'. */
function dia(fecha: string | null): string {
    const partes = fecha?.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!partes) {
        return 'Sin fecha';
    }

    const [, a, m, d] = partes;

    return DIA.format(new Date(Number(a), Number(m) - 1, Number(d)));
}

/**
 * Agrupa por día **respetando el orden que llegó** (el legacy ordena por id):
 * si una fecha vuelve a aparecer más abajo, se abre otro grupo en vez de
 * mover el renglón de lugar.
 */
function porDia(notas: Nota[]): { fecha: string | null; notas: Nota[] }[] {
    const grupos: { fecha: string | null; notas: Nota[] }[] = [];

    for (const nota of notas) {
        const ultimo = grupos.at(-1);

        if (ultimo && ultimo.fecha === nota.fecha) {
            ultimo.notas.push(nota);
        } else {
            grupos.push({ fecha: nota.fecha, notas: [nota] });
        }
    }

    return grupos;
}

export default function NotasIndex({
    notas,
    filtros,
}: {
    notas: Nota[];
    filtros: Filtros;
}) {
    const [formMode, setFormMode] = useState<'create' | 'edit' | null>(null);
    const [editando, setEditando] = useState<Nota | null>(null);
    const [eliminar, setEliminar] = useState<Nota | null>(null);
    const [desde, setDesde] = useState(filtros.desde);
    const [hasta, setHasta] = useState(filtros.hasta);
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

    const abiertos = notas.reduce(
        (total, n) => total + n.pendientes.filter((p) => !p.realizado).length,
        0,
    );

    const buscar = () =>
        router.get(
            notasIndex.url(),
            { desde, hasta },
            { preserveState: true, preserveScroll: true },
        );

    const abrirCrear = () => {
        setEditando(null);
        form.clearErrors();
        form.setData(VACIO);
        setFormMode('create');
    };

    const abrirEditar = (n: Nota) => {
        setEditando(n);
        form.clearErrors();
        form.setData({
            id: n.id,
            nota: n.nota ?? '',
            fecha: n.fecha ?? '',
            evento_id: n.evento_id,
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

    return (
        <>
            <Head title="Notas" />
            <div className="space-y-4 p-4">
                <Encabezado
                    titulo="Notas"
                    resumen={
                        <>
                            <span className="font-medium text-foreground tabular-nums">
                                {notas.length}
                            </span>{' '}
                            {notas.length === 1 ? 'nota' : 'notas'}
                            {abiertos > 0 && (
                                <>
                                    {' · '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {abiertos}
                                    </span>{' '}
                                    {abiertos === 1
                                        ? 'pendiente abierto'
                                        : 'pendientes abiertos'}
                                </>
                            )}
                        </>
                    }
                    acciones={
                        <Button onClick={abrirCrear}>
                            <Plus className="mr-2 size-4" /> Nueva nota
                        </Button>
                    }
                />

                {/* El legacy abre siempre en una ventana de fechas (ayer y hoy);
                    dejarla a la vista evita preguntarse dónde está el resto. */}
                <div className="flex flex-wrap items-end gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <Field className="w-40">
                        <Label htmlFor="desde">Notas desde</Label>
                        <Input
                            id="desde"
                            type="date"
                            value={desde}
                            onChange={(e) => setDesde(e.target.value)}
                        />
                    </Field>
                    <Field className="w-40">
                        <Label htmlFor="hasta">Hasta</Label>
                        <Input
                            id="hasta"
                            type="date"
                            value={hasta}
                            onChange={(e) => setHasta(e.target.value)}
                        />
                    </Field>
                    <Button variant="outline" onClick={buscar}>
                        <Search className="mr-2 size-4" /> Buscar
                    </Button>
                    <p className="ml-auto text-sm text-muted-foreground">
                        Mostrando del {formatDate(filtros.desde)} al{' '}
                        {formatDate(filtros.hasta)}.
                    </p>
                </div>

                {notas.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-sidebar-border/70 px-6 py-12 text-center text-sm text-muted-foreground">
                        No hay notas entre el {formatDate(filtros.desde)} y el{' '}
                        {formatDate(filtros.hasta)}. Amplía el rango o crea una
                        nota nueva.
                    </p>
                ) : (
                    /* Libro de registro: cada día abre su banda y los asientos
                       de ese día se leen de arriba abajo. */
                    <div className="space-y-6">
                        {porDia(notas).map((grupo, i) => (
                            <section key={`${grupo.fecha}-${i}`}>
                                <h2 className="mb-2 flex items-center gap-3 text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                    <span className="whitespace-nowrap">
                                        {dia(grupo.fecha)}
                                    </span>
                                    <span className="h-px flex-1 bg-border" />
                                    <span className="whitespace-nowrap tabular-nums">
                                        {grupo.notas.length}{' '}
                                        {grupo.notas.length === 1
                                            ? 'nota'
                                            : 'notas'}
                                    </span>
                                </h2>

                                <div className="divide-y divide-sidebar-border/70 overflow-hidden rounded-xl border border-sidebar-border/70 bg-card">
                                    {grupo.notas.map((nota) => (
                                        <Asiento
                                            key={nota.id}
                                            nota={nota}
                                            onEditar={() => abrirEditar(nota)}
                                            onEliminar={() => setEliminar(nota)}
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
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
                size="md"
                title={formMode === 'edit' ? 'Editar nota' : 'Nueva nota'}
                description="Captura el contenido de la nota."
                submitLabel={
                    formMode === 'edit' ? 'Guardar cambios' : 'Guardar'
                }
                processing={form.processing}
                onSubmit={enviar}
            >
                <Field>
                    <Label htmlFor="nota-texto">Nota</Label>
                    <textarea
                        id="nota-texto"
                        className="min-h-28 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        value={form.data.nota}
                        onChange={(e) => form.setData('nota', e.target.value)}
                    />
                </Field>

                <FormInputField
                    id="nota-fecha"
                    type="date"
                    label="Fecha"
                    value={form.data.fecha}
                    error={form.errors.fecha}
                    onChange={(e) => form.setData('fecha', e.target.value)}
                />

                <AsyncSearchPicker
                    label="Vincular a evento (opcional)"
                    searchUrl="/notas/eventos/buscar"
                    value={form.data.evento_id}
                    valueLabel={editando?.evento_titulo}
                    error={form.errors.evento_id}
                    onChange={(id) => form.setData('evento_id', id)}
                    placeholder="Buscar evento por título..."
                />
            </CrudFormDialog>

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar nota"
                entityLabel="la nota"
                itemName={eliminar?.nota?.slice(0, 40) ?? `#${eliminar?.id}`}
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

/** Un asiento del registro: el texto de la nota y lo que cuelga de ella. */
function Asiento({
    nota,
    onEditar,
    onEliminar,
}: {
    nota: Nota;
    onEditar: () => void;
    onEliminar: () => void;
}) {
    const [nuevo, setNuevo] = useState('');
    const [agregando, setAgregando] = useState(false);
    const [editandoId, setEditandoId] = useState<number | null>(null);
    const [borrador, setBorrador] = useState('');

    const abiertos = nota.pendientes.filter((p) => !p.realizado).length;

    const agregar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (nuevo.trim() === '') {
            return;
        }

        router.post(
            `/notas/${nota.id}/pendientes`,
            { texto: nuevo },
            {
                preserveScroll: true,
                onSuccess: () => setNuevo(''),
                onError: () => toast.error('No se pudo agregar el pendiente.'),
            },
        );
    };

    const alternar = (p: Pendiente) =>
        router.put(
            `/notas/pendientes/${p.id}/toggle`,
            {},
            { preserveScroll: true },
        );

    const guardarTexto = (p: Pendiente) => {
        if (borrador.trim() === '' || borrador === p.texto) {
            setEditandoId(null);

            return;
        }

        router.put(
            `/notas/pendientes/${p.id}`,
            { texto: borrador },
            {
                preserveScroll: true,
                onSuccess: () => setEditandoId(null),
                onError: () => toast.error('No se pudo guardar el pendiente.'),
            },
        );
    };

    const borrarPendiente = (p: Pendiente) =>
        router.delete(`/notas/pendientes/${p.id}`, { preserveScroll: true });

    const { tipo, folio, cuerpo } = leerAsiento(nota.nota);

    return (
        <article className="relative flex flex-col gap-2 p-4 sm:flex-row sm:gap-4">
            {/* La columna de margen del libro de gobierno: el tipo de asiento y
                su folio, que es por donde se le busca. Sangra hasta el canto de
                la hoja para que se lea como una columna reglada y no como un
                dato más del renglón. */}
            <div className="flex items-baseline gap-2 sm:-my-4 sm:-ml-4 sm:block sm:w-40 sm:shrink-0 sm:bg-muted/40 sm:p-4">
                {tipo && (
                    <p className="text-[10px] leading-4 font-medium tracking-[0.14em] text-muted-foreground uppercase">
                        {tipo}
                    </p>
                )}
                {folio && (
                    <p className="font-mono text-xs font-medium break-words tabular-nums">
                        {folio}
                    </p>
                )}
            </div>

            <div className="min-w-0 flex-1">
                {/* El hueco de la derecha es para el menú, que va encimado
                    para que caiga en el mismo eje que el de los pendientes. */}
                {cuerpo !== '' && (
                    <p className="max-w-[72ch] pr-10 text-sm leading-relaxed tracking-[0.01em] whitespace-pre-wrap">
                        {cuerpo}
                    </p>
                )}

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="absolute top-3 right-3 size-8"
                        >
                            <MoreHorizontal className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={onEditar}>
                            <Pencil className="mr-2 size-4" /> Editar nota
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={() => setAgregando(true)}>
                            <ListPlus className="mr-2 size-4" /> Agregar
                            pendiente
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={onEliminar}
                        >
                            <Trash2 className="mr-2 size-4" /> Eliminar nota
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Lo que el legacy enseñaba al picarle a "Vinculado", sin el clic. */}
                {nota.evento && (
                    <div className="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-lg bg-muted/50 px-3 py-2 text-xs">
                        <span className="font-medium">
                            {nota.evento.titulo}
                        </span>
                        {nota.evento.fecha && (
                            <span className="inline-flex items-center gap-1 text-muted-foreground tabular-nums">
                                <CalendarClock className="size-3" />
                                {formatDate(nota.evento.fecha)}
                                {nota.evento.hora && ` · ${nota.evento.hora}`}
                            </span>
                        )}
                        {nota.evento.lugar && (
                            <span className="inline-flex items-center gap-1 text-muted-foreground">
                                <MapPin className="size-3" />
                                {nota.evento.lugar}
                            </span>
                        )}
                    </div>
                )}

                {nota.pendientes.length > 0 && (
                    <div className="mt-3 space-y-1 border-l-2 border-sidebar-border/70 pl-3">
                        <div className="mb-1.5">
                            <Badge
                                variant={abiertos > 0 ? 'default' : 'secondary'}
                                className="tabular-nums"
                            >
                                {abiertos > 0
                                    ? `${abiertos} por hacer`
                                    : 'Todo listo'}
                            </Badge>
                        </div>

                        {nota.pendientes.map((p) => (
                            <div
                                key={p.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                {editandoId === p.id ? (
                                    <>
                                        <Input
                                            autoFocus
                                            value={borrador}
                                            className="h-8 flex-1 text-sm"
                                            onChange={(e) =>
                                                setBorrador(e.target.value)
                                            }
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    guardarTexto(p);
                                                }

                                                if (e.key === 'Escape') {
                                                    setEditandoId(null);
                                                }
                                            }}
                                        />
                                        <button
                                            type="button"
                                            title="Guardar"
                                            onClick={() => guardarTexto(p)}
                                        >
                                            <Check className="size-4 text-muted-foreground" />
                                        </button>
                                        <button
                                            type="button"
                                            title="Cancelar"
                                            onClick={() => setEditandoId(null)}
                                        >
                                            <X className="size-4 text-muted-foreground" />
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <Checkbox
                                            checked={p.realizado}
                                            onCheckedChange={() => alternar(p)}
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
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-6 shrink-0 text-muted-foreground"
                                                >
                                                    <MoreHorizontal className="size-3.5" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onClick={() => {
                                                        setBorrador(p.texto);
                                                        setEditandoId(p.id);
                                                    }}
                                                >
                                                    <Pencil className="mr-2 size-4" />{' '}
                                                    Editar pendiente
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onClick={() =>
                                                        borrarPendiente(p)
                                                    }
                                                >
                                                    <Trash2 className="mr-2 size-4" />{' '}
                                                    Eliminar pendiente
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* La mayoría de las notas no llevan pendientes: el campo sólo
                aparece cuando ya hay alguno o cuando se pide desde el menú,
                para no repetirlo 50 veces. */}
                {(agregando || nota.pendientes.length > 0) && (
                    <form
                        className="mt-2 flex max-w-md gap-2"
                        onSubmit={agregar}
                    >
                        <Input
                            autoFocus={agregando}
                            value={nuevo}
                            placeholder="Agregar pendiente..."
                            className="h-8 text-sm"
                            onChange={(e) => setNuevo(e.target.value)}
                        />
                        <Button type="submit" size="sm" variant="outline">
                            <Plus className="size-4" />
                        </Button>
                    </form>
                )}
            </div>
        </article>
    );
}

NotasIndex.layout = {
    breadcrumbs: [{ title: 'Notas', href: '/notas' }],
};
