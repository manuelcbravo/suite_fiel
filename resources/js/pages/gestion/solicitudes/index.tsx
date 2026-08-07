import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronDown,
    ClipboardCheck,
    ClipboardList,
    FolderSearch,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Trash2,
    UserPen,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { destroy, index } from '@/routes/gestion/solicitudes';
import { AtenderDialog } from './atender-dialog';
import { selloDe } from './estatus';
import { SeguimientoDialog } from './seguimiento-dialog';
import { SolicitudFormDialog } from './solicitud-form-dialog';
import type {
    CamposBusqueda,
    Filtros,
    Grupo,
    Opciones,
    PaginacionBloque,
    Solicitud,
} from './types';
import {
    puedeAtender,
    puedeEditar,
    puedeEliminar,
    puedeSeguimiento,
    puedeVerificar,
} from './types';
import { VerificacionDialog } from './verificacion-dialog';

/** Casillas "buscar por" del legacy, en el mismo orden. */
const CAMPOS: { clave: keyof CamposBusqueda; etiqueta: string }[] = [
    { clave: 'solicitante', etiqueta: 'Nombre del solicitante' },
    { clave: 'telefono', etiqueta: 'Teléfono del solicitante' },
    { clave: 'celular', etiqueta: 'Celular del solicitante' },
    { clave: 'organizacion', etiqueta: 'Organización solicitante' },
    { clave: 'tel_org', etiqueta: 'Teléfono de la organización' },
    { clave: 'cel_org', etiqueta: 'Celular de la organización' },
    { clave: 'solicitud', etiqueta: 'Solicitud' },
    { clave: 'folio', etiqueta: 'Folio de la solicitud' },
    { clave: 'beneficiario', etiqueta: 'Beneficiario' },
];

type Accion =
    // Sin grupo = alta desde el botón general: el diálogo pide el solicitante.
    | { tipo: 'alta'; grupo: Grupo | null }
    | { tipo: 'editar'; solicitud: Solicitud }
    | { tipo: 'seguimiento'; solicitud: Solicitud }
    | { tipo: 'atender'; solicitud: Solicitud }
    | { tipo: 'verificar'; solicitud: Solicitud }
    | null;

export default function SolicitudesIndex({
    buscado,
    filtros,
    personas,
    organizaciones,
    paginacion,
    opciones,
}: {
    buscado: boolean;
    filtros: Filtros;
    personas: Grupo[];
    organizaciones: Grupo[];
    paginacion: {
        personas: PaginacionBloque;
        organizaciones: PaginacionBloque;
    };
    opciones: Opciones;
}) {
    const [accion, setAccion] = useState<Accion>(null);
    const [eliminar, setEliminar] = useState<Solicitud | null>(null);
    const [form, setForm] = useState<Filtros>(filtros);
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const buscar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            index.url(),
            {
                q: form.q,
                dependencia_id: form.dependencia_id ?? '',
                estatus: form.estatus ?? '',
                campos: Object.fromEntries(
                    Object.entries(form.campos).map(([k, v]) => [
                        k,
                        v ? '1' : '0',
                    ]),
                ),
            },
            { preserveState: true, preserveScroll: true },
        );
    };

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
                        <Button
                            onClick={() =>
                                setAccion({ tipo: 'alta', grupo: null })
                            }
                        >
                            <Plus className="mr-2 size-4" /> Agregar solicitud
                        </Button>
                    </div>
                </div>

                {/* Buscador del legacy: texto + dependencia + estatus + campos. */}
                <form
                    className="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4"
                    onSubmit={buscar}
                >
                    <div className="grid items-end gap-3 md:grid-cols-12">
                        <div className="space-y-1.5 md:col-span-5">
                            <Eyebrow>Qué buscas</Eyebrow>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    className="pl-9"
                                    placeholder="Nombre, teléfono, folio o texto de la solicitud"
                                    value={form.q}
                                    onChange={(e) =>
                                        setForm({ ...form, q: e.target.value })
                                    }
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5 md:col-span-3">
                            <Eyebrow>Dependencia</Eyebrow>
                            <SelectField
                                label=""
                                placeholder="Todas"
                                emptyLabel="Todas"
                                value={form.dependencia_id}
                                options={opciones.dependencias}
                                onChange={(v) =>
                                    setForm({ ...form, dependencia_id: v })
                                }
                            />
                        </div>
                        <div className="space-y-1.5 md:col-span-2">
                            <Eyebrow>Estatus</Eyebrow>
                            <SelectField
                                label=""
                                placeholder="Todos"
                                emptyLabel="Todos"
                                value={form.estatus}
                                options={opciones.estatus}
                                onChange={(v) =>
                                    setForm({ ...form, estatus: v })
                                }
                            />
                        </div>
                        <div className="md:col-span-2">
                            <Button type="submit" className="w-full">
                                <Search className="mr-2 size-4" /> Buscar
                            </Button>
                        </div>
                    </div>

                    {/* Los 9 campos del legacy, como interruptores: ocupan una
                        línea en vez de una rejilla y se ve cuáles están activos. */}
                    <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                        <Eyebrow className="mr-1">Buscar por</Eyebrow>
                        {CAMPOS.map(({ clave, etiqueta }) => (
                            <label
                                key={clave}
                                className="cursor-pointer text-sm"
                            >
                                <input
                                    type="checkbox"
                                    className="peer sr-only"
                                    checked={form.campos[clave]}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            campos: {
                                                ...form.campos,
                                                [clave]: e.target.checked,
                                            },
                                        })
                                    }
                                />
                                <span className="inline-flex rounded-full border px-3 py-1 text-xs text-muted-foreground transition-colors peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 hover:bg-accent">
                                    {etiqueta}
                                </span>
                            </label>
                        ))}
                    </div>
                </form>

                {!buscado ? (
                    <Vacio
                        titulo="Empieza por buscar"
                        texto="Escribe un nombre, un folio o filtra por dependencia o estatus para abrir los expedientes."
                    />
                ) : personas.length === 0 && organizaciones.length === 0 ? (
                    <Vacio
                        titulo="Sin coincidencias"
                        texto="Ningún solicitante coincide con esos filtros. Prueba con menos campos marcados o quita el estatus."
                    />
                ) : (
                    <>
                        <div className="flex justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/directorio/beneficiarios">
                                    Agregar Solicitante
                                </Link>
                            </Button>
                        </div>

                        {/* Cada bloque sólo aparece si trajo resultados, para
                            no repetir dos veces el mismo "sin resultados". */}
                        {personas.length > 0 && (
                            <TablaSolicitantes
                                titulo="Solicitantes"
                                grupos={personas}
                                paginacion={paginacion.personas}
                                filtros={filtros}
                                onAccion={setAccion}
                                onEliminar={setEliminar}
                            />
                        )}

                        {organizaciones.length > 0 && (
                            <TablaSolicitantes
                                titulo="Organizaciones"
                                grupos={organizaciones}
                                paginacion={paginacion.organizaciones}
                                filtros={filtros}
                                onAccion={setAccion}
                                onEliminar={setEliminar}
                            />
                        )}
                    </>
                )}
            </div>

            {(accion?.tipo === 'alta' || accion?.tipo === 'editar') && (
                <SolicitudFormDialog
                    solicitud={accion.tipo === 'editar' ? accion.solicitud : null}
                    grupo={accion.tipo === 'alta' ? accion.grupo : null}
                    opciones={opciones}
                    onClose={() => setAccion(null)}
                />
            )}

            {accion?.tipo === 'seguimiento' && (
                <SeguimientoDialog
                    solicitud={accion.solicitud}
                    opciones={opciones}
                    onClose={() => setAccion(null)}
                />
            )}

            {accion?.tipo === 'atender' && (
                <AtenderDialog
                    solicitud={accion.solicitud}
                    opciones={opciones}
                    onClose={() => setAccion(null)}
                />
            )}

            {accion?.tipo === 'verificar' && (
                <VerificacionDialog
                    solicitud={accion.solicitud}
                    onClose={() => setAccion(null)}
                />
            )}

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar solicitud"
                entityLabel="la solicitud"
                itemName={
                    eliminar?.folio_sistema ??
                    eliminar?.folio ??
                    `#${eliminar?.id}`
                }
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

/**
 * Tabla agrupada del legacy: una fila por solicitante y, debajo, una fila por
 * cada una de sus solicitudes con los botones que apliquen a su estatus.
 */
function TablaSolicitantes({
    titulo,
    grupos,
    paginacion,
    filtros,
    onAccion,
    onEliminar,
}: {
    titulo: string;
    grupos: Grupo[];
    paginacion: PaginacionBloque;
    /** Los filtros aplicados, para pedir más solicitudes con el mismo criterio. */
    filtros: Filtros;
    onAccion: (accion: Accion) => void;
    onEliminar: (solicitud: Solicitud) => void;
}) {
    const solicitudes = grupos.reduce(
        (total, g) => total + g.solicitudes.length,
        0,
    );

    return (
        <div className="space-y-3">
            <div className="flex items-baseline gap-3">
                <h2 className="text-base font-semibold tracking-tight">
                    {titulo}
                </h2>
                <span className="text-xs text-muted-foreground">
                    {grupos.length}{' '}
                    {grupos.length === 1 ? 'expediente' : 'expedientes'} ·{' '}
                    {solicitudes}{' '}
                    {solicitudes === 1 ? 'solicitud' : 'solicitudes'}
                </span>
            </div>

            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card">
                <table className="w-full min-w-[900px] border-collapse text-sm">
                    <thead>
                        <tr className="bg-muted/40 text-left">
                            {[
                                'Solicitante',
                                'Localidad',
                                'Teléfonos',
                                'Folio / Solicitud',
                                'Beneficiario',
                                'Estatus',
                                '',
                            ].map((th, i) => (
                                <th
                                    key={i}
                                    className="border-b px-3 py-2.5 text-[11px] font-medium tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                >
                                    {th}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {grupos.map((grupo, i) => (
                            <FilasGrupo
                                key={`${grupo.tipo}-${grupo.id}`}
                                grupo={grupo}
                                primero={i === 0}
                                filtros={filtros}
                                onAccion={onAccion}
                                onEliminar={onEliminar}
                            />
                        ))}
                    </tbody>
                </table>
            </div>

            {paginacion && paginacion.lastPage > 1 && (
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {paginacion.total} solicitantes · página{' '}
                        {paginacion.currentPage} de {paginacion.lastPage}
                    </span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!paginacion.prevUrl}
                            onClick={() =>
                                paginacion.prevUrl &&
                                router.visit(paginacion.prevUrl, {
                                    preserveState: true,
                                })
                            }
                        >
                            Anterior
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!paginacion.nextUrl}
                            onClick={() =>
                                paginacion.nextUrl &&
                                router.visit(paginacion.nextUrl, {
                                    preserveState: true,
                                })
                            }
                        >
                            Siguiente
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

/** Rótulo pequeño que nombra un control o una zona de la pantalla. */
function Eyebrow({
    children,
    className = '',
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <span
            className={`block text-[11px] font-medium tracking-wider text-muted-foreground uppercase ${className}`}
        >
            {children}
        </span>
    );
}

/** Pantalla sin resultados: dice qué pasó y qué hacer. */
function Vacio({ titulo, texto }: { titulo: string; texto: string }) {
    return (
        <div className="rounded-xl border border-dashed border-sidebar-border/70 px-6 py-14 text-center">
            <FolderSearch className="mx-auto size-7 text-muted-foreground/60" />
            <p className="mt-3 font-medium">{titulo}</p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                {texto}
            </p>
        </div>
    );
}

/** Menú de acciones de una fila: icono + texto, para que se entienda qué hace. */
function MenuAcciones({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="size-8">
                        <MoreHorizontal className="size-4" />
                        <span className="sr-only">Acciones</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">{children}</DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

function FilasGrupo({
    grupo,
    primero,
    filtros,
    onAccion,
    onEliminar,
}: {
    grupo: Grupo;
    /** El primer expediente no lleva la línea gruesa de separación. */
    primero: boolean;
    filtros: Filtros;
    onAccion: (accion: Accion) => void;
    onEliminar: (solicitud: Solicitud) => void;
}) {
    // Las que llegaron en la carga inicial más las tandas que se pidan después.
    const [extra, setExtra] = useState<Solicitud[]>([]);
    const [cargando, setCargando] = useState(false);

    const solicitudes = [...grupo.solicitudes, ...extra];
    const faltan = grupo.solicitudes_total - solicitudes.length;

    const cargarMas = () => {
        setCargando(true);
        const params = new URLSearchParams({ desde: String(solicitudes.length) });

        if (filtros.estatus !== null) {
            params.set('estatus', String(filtros.estatus));
        }

        if (filtros.dependencia_id !== null) {
            params.set('dependencia_id', String(filtros.dependencia_id));
        }

        fetch(
            `/gestion/solicitantes/${grupo.tipo}/${grupo.id}/solicitudes?${params}`,
            { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
        )
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => setExtra((previas) => [...previas, ...(data as Solicitud[])]))
            .catch(() => toast.error('No se pudieron cargar más solicitudes.'))
            .finally(() => setCargando(false));
    };

    // Abre el Directorio con el diálogo de edición de este solicitante ya
    // puesto, y con su nombre buscado para que al cerrarlo quede en pantalla.
    const destino = `/directorio?${new URLSearchParams({
        tab: grupo.tipo === 'organizacion' ? 'organizaciones' : 'individuos',
        editar: String(grupo.id),
        q: grupo.nombre,
    })}`;

    const separador = primero ? '' : 'border-t-2 border-t-border';

    return (
        <>
            <tr className={`bg-muted/30 ${separador}`}>
                <td className="px-3 py-3">
                    <span className="font-semibold tracking-tight">
                        {grupo.nombre}
                    </span>
                    <span className="ml-2 rounded-full bg-background px-2 py-0.5 text-[11px] text-muted-foreground tabular-nums">
                        {grupo.solicitudes_total}
                    </span>
                </td>
                <td className="px-3 py-3 text-muted-foreground">
                    {grupo.localidad ?? '—'}
                </td>
                <td className="px-3 py-3 text-muted-foreground tabular-nums">
                    {grupo.telefonos ?? '—'}
                </td>
                <td className="px-3 py-3" colSpan={3} />
                {/* En la fila del solicitante van como botones con texto: son
                    sólo dos y así no se confunden con el menú de la solicitud. */}
                <td className="px-3 py-3">
                    <div className="flex justify-end gap-2 whitespace-nowrap">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={destino}>
                                <UserPen className="mr-2 size-4" /> Editar
                                solicitante
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onAccion({ tipo: 'alta', grupo })}
                        >
                            <Plus className="mr-2 size-4" /> Agregar solicitud
                        </Button>
                    </div>
                </td>
            </tr>

            {solicitudes.map((s) => (
                <tr key={s.id} className="transition-colors hover:bg-muted/30">
                    <td className="border-b px-3 py-2.5" colSpan={3} />
                    {/* El riel repite el color del sello: bajando la vista se
                        lee el estado de todo el expediente sin leer texto. */}
                    <td
                        className={`border-b border-l-[3px] px-3 py-2.5 ${selloDe(s.status).riel}`}
                    >
                        <span className="font-mono text-xs font-medium tabular-nums">
                            {s.folio_sistema ?? s.folio ?? `#${s.id}`}
                        </span>
                        <p className="mt-0.5 line-clamp-2 max-w-md text-muted-foreground">
                            {s.solicitud ?? '—'}
                        </p>
                    </td>
                    <td className="border-b px-3 py-2.5">
                        {s.beneficiario_nombre ?? '—'}
                    </td>
                    <td className="border-b px-3 py-2.5">
                        <span
                            className={`inline-flex w-fit rounded-md border px-2 py-0.5 text-[11px] font-medium tracking-wide whitespace-nowrap ${selloDe(s.status).sello}`}
                        >
                            {s.status_label}
                        </span>
                        {s.status === 2 && !s.notificacion && (
                            <span className="mt-1 block text-[11px] font-semibold tracking-wide text-red-600 uppercase dark:text-red-400">
                                Pendiente notificación
                            </span>
                        )}
                    </td>
                    <td className="border-b px-3 py-2.5">
                        <MenuAcciones>
                            {puedeSeguimiento(s) && (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onAccion({
                                            tipo: 'seguimiento',
                                            solicitud: s,
                                        })
                                    }
                                >
                                    <ArrowRight className="mr-2 size-4" />{' '}
                                    Seguimiento
                                </DropdownMenuItem>
                            )}
                            {puedeAtender(s) && (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onAccion({
                                            tipo: 'atender',
                                            solicitud: s,
                                        })
                                    }
                                >
                                    <RefreshCw className="mr-2 size-4 text-emerald-600" />{' '}
                                    Atender / Resolver
                                </DropdownMenuItem>
                            )}
                            {puedeVerificar(s) && (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onAccion({
                                            tipo: 'verificar',
                                            solicitud: s,
                                        })
                                    }
                                >
                                    <ClipboardCheck className="mr-2 size-4" />{' '}
                                    Verificar atención
                                </DropdownMenuItem>
                            )}
                            {puedeEditar(s) && (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onAccion({
                                            tipo: 'editar',
                                            solicitud: s,
                                        })
                                    }
                                >
                                    <Pencil className="mr-2 size-4" /> Editar
                                    solicitud
                                </DropdownMenuItem>
                            )}
                            {puedeEliminar(s) && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={() => onEliminar(s)}
                                    >
                                        <Trash2 className="mr-2 size-4" />{' '}
                                        Eliminar solicitud
                                    </DropdownMenuItem>
                                </>
                            )}
                        </MenuAcciones>
                    </td>
                </tr>
            ))}

            {faltan > 0 && (
                <tr>
                    <td className="px-3 py-2.5" colSpan={3} />
                    <td className="border-b px-3 py-2.5" colSpan={4}>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={cargando}
                            onClick={cargarMas}
                            className="text-muted-foreground"
                        >
                            <ChevronDown className="mr-2 size-4" />
                            {cargando
                                ? 'Cargando…'
                                : `Ver ${Math.min(faltan, 30)} solicitudes más de ${grupo.nombre} (faltan ${faltan})`}
                        </Button>
                    </td>
                </tr>
            )}
        </>
    );
}

SolicitudesIndex.layout = {
    breadcrumbs: [
        { title: 'Gestión', href: index() },
        { title: 'Solicitudes', href: index() },
    ],
};
