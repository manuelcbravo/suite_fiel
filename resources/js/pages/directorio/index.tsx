import { Head, router, usePage } from '@inertiajs/react';
import {
    Building2,
    ContactRound,
    FolderSearch,
    LayoutGrid,
    Plus,
    Rows3,
    Search,
    SlidersHorizontal,
    Truck,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ComentariosDialog } from '@/components/comentarios-dialog';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { index } from '@/routes/directorio';
import { destroy as destroyBeneficiario } from '@/routes/directorio/beneficiarios';
import { destroy as destroyOrganizacion } from '@/routes/directorio/organizaciones';
import { destroy as destroyProveedor } from '@/routes/directorio/proveedores';
import { BeneficiarioFormDialog } from './beneficiario-form-dialog';
import { FichaDialog } from './ficha-dialog';
import { OrganizacionFormDialog } from './organizacion-form-dialog';
import { ProveedorFormDialog } from './proveedor-form-dialog';
import { Tabla } from './tabla';
import { Tarjeta } from './tarjeta';
import type {
    CamposBusqueda,
    Ficha,
    Filtros,
    Opciones,
    PaginacionBloque,
    Pestana,
} from './types';
import { GENEROS } from './types';

/** Las tres pestañas del legacy, con su nombre y el rótulo del botón de alta. */
const PESTANAS: {
    clave: Pestana;
    titulo: string;
    alta: string;
    icono: typeof Users;
}[] = [
    { clave: 'individuos', titulo: 'Individuos', alta: 'Agregar individuo', icono: Users },
    {
        clave: 'organizaciones',
        titulo: 'Asociaciones / Grupos organizados',
        alta: 'Agregar organización',
        icono: Building2,
    },
    {
        clave: 'proveedores',
        titulo: 'Proveedores / Contratistas',
        alta: 'Agregar proveedor',
        icono: Truck,
    },
];

/** Casillas "Buscar en" del legacy. */
const CAMPOS: { clave: keyof CamposBusqueda; etiqueta: string }[] = [
    { clave: 'nombres', etiqueta: 'Nombres' },
    { clave: 'comentarios', etiqueta: 'Comentarios' },
    { clave: 'trabajo', etiqueta: 'Trabajo' },
];

const RUTA_ELIMINAR = {
    beneficiarios: destroyBeneficiario,
    organizaciones: destroyOrganizacion,
    proveedores: destroyProveedor,
};

/** Cómo se listan los resultados; la preferencia se recuerda en el navegador. */
type Vista = 'tarjetas' | 'tabla';
const VISTA_GUARDADA = 'directorio:vista';

function vistaInicial(): Vista {
    if (typeof window === 'undefined') {
        return 'tarjetas';
    }

    return localStorage.getItem(VISTA_GUARDADA) === 'tabla' ? 'tabla' : 'tarjetas';
}

/**
 * En la base sólo los beneficiarios y las organizaciones pueden ser
 * solicitantes, así que en proveedores no hay a dónde ir.
 */
const SOLICITANTE: Partial<Record<Pestana, 'beneficiario' | 'organizacion'>> = {
    individuos: 'beneficiario',
    organizaciones: 'organizacion',
};

export default function DirectorioIndex({
    buscado,
    tab,
    editarId,
    filtros,
    individuos,
    organizaciones,
    proveedores,
    paginacion,
    opciones,
}: {
    buscado: boolean;
    tab: Pestana;
    /** Id que llega por URL para abrir el diálogo de edición al cargar. */
    editarId: number | null;
    filtros: Filtros;
    individuos: Ficha[];
    organizaciones: Ficha[];
    proveedores: Ficha[];
    paginacion: Record<Pestana, PaginacionBloque>;
    opciones: Opciones;
}) {
    const [activa, setActiva] = useState<Pestana>(tab);
    const [vista, setVista] = useState<Vista>(vistaInicial);
    const [form, setForm] = useState<Filtros>(filtros);
    const [masFiltros, setMasFiltros] = useState(
        filtros.estado_id !== null ||
            filtros.profesion_id !== null ||
            filtros.sector_id !== null ||
            filtros.genero !== null,
    );
    // Al llegar desde Solicitudes con ?editar=, el diálogo abre solo.
    const [editar, setEditar] = useState<{ tipo: Pestana; id: number | null } | null>(
        editarId !== null ? { tipo: tab, id: editarId } : null,
    );
    const [ver, setVer] = useState<Ficha | null>(null);
    const [comentarios, setComentarios] = useState<Ficha | null>(null);
    const [eliminar, setEliminar] = useState<Ficha | null>(null);
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const cambiarVista = (nueva: Vista) => {
        setVista(nueva);
        localStorage.setItem(VISTA_GUARDADA, nueva);
    };

    /** Abre Solicitudes con el expediente de este solicitante. */
    const abrirSolicitudes = (ficha: Ficha) => {
        const tipo = SOLICITANTE[activa];

        if (!tipo) {
            return;
        }

        router.visit(
            `/gestion/solicitudes?${new URLSearchParams({
                solicitante_tipo: tipo,
                solicitante_id: String(ficha.id),
            })}`,
        );
    };

    const fichas: Record<Pestana, Ficha[]> = {
        individuos,
        organizaciones,
        proveedores,
    };

    const buscar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            index.url(),
            {
                q: form.q,
                tab: activa,
                campos: Object.fromEntries(
                    Object.entries(form.campos).map(([k, v]) => [k, v ? '1' : '0']),
                ),
                estado_id: form.estado_id ?? '',
                municipio_id: form.municipio_id ?? '',
                localidad_id: form.localidad_id ?? '',
                profesion_id: form.profesion_id ?? '',
                sector_id: form.sector_id ?? '',
                genero: form.genero ?? '',
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const pestanaActiva = PESTANAS.find((p) => p.clave === activa) ?? PESTANAS[0];
    const lista = fichas[activa];
    const paginas = paginacion[activa];

    return (
        <>
            <Head title="Directorio" />
            <div className="space-y-4 rounded-xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <ContactRound className="size-5 text-primary" />
                            <div>
                                <h1 className="text-xl font-semibold">Directorio</h1>
                                <p className="text-sm text-muted-foreground">
                                    Individuos, organizaciones y proveedores.
                                </p>
                            </div>
                        </div>
                        <Button onClick={() => setEditar({ tipo: activa, id: null })}>
                            <Plus className="mr-2 size-4" /> {pestanaActiva.alta}
                        </Button>
                    </div>
                </div>

                {/* Buscador del legacy: texto + "Buscar en" + más filtros. */}
                <form
                    className="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4"
                    onSubmit={buscar}
                >
                    <div className="grid items-end gap-3 md:grid-cols-12">
                        <div className="space-y-1.5 md:col-span-7">
                            <Eyebrow>Qué buscas</Eyebrow>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    className="pl-9"
                                    placeholder="Nombre, alias, organización, RFC…"
                                    value={form.q}
                                    onChange={(e) =>
                                        setForm({ ...form, q: e.target.value })
                                    }
                                />
                            </div>
                        </div>
                        <div className="md:col-span-3">
                            <Button
                                type="button"
                                variant={masFiltros ? 'secondary' : 'outline'}
                                className="w-full"
                                onClick={() => setMasFiltros((v) => !v)}
                            >
                                <SlidersHorizontal className="mr-2 size-4" />
                                {masFiltros ? 'Ocultar filtros' : 'Más filtros'}
                            </Button>
                        </div>
                        <div className="md:col-span-2">
                            <Button type="submit" className="w-full">
                                <Search className="mr-2 size-4" /> Buscar
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                        <Eyebrow className="mr-1">Buscar en</Eyebrow>
                        {CAMPOS.map(({ clave, etiqueta }) => (
                            <label key={clave} className="cursor-pointer text-sm">
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

                    {masFiltros && (
                        <div className="space-y-3 border-t pt-3">
                            <UbicacionSelects
                                estados={opciones.estados}
                                value={{
                                    estado_id: form.estado_id,
                                    municipio_id: form.municipio_id,
                                    localidad_id: form.localidad_id,
                                }}
                                onChange={(patch) =>
                                    setForm({
                                        ...form,
                                        ...('estado_id' in patch
                                            ? { estado_id: patch.estado_id ?? null }
                                            : {}),
                                        ...('municipio_id' in patch
                                            ? { municipio_id: patch.municipio_id ?? null }
                                            : {}),
                                        ...('localidad_id' in patch
                                            ? { localidad_id: patch.localidad_id ?? null }
                                            : {}),
                                    })
                                }
                            />
                            <div className="grid gap-3 sm:grid-cols-3">
                                <SelectField
                                    label="Profesión"
                                    emptyLabel="Todas"
                                    value={form.profesion_id}
                                    options={opciones.profesiones}
                                    onChange={(v) => setForm({ ...form, profesion_id: v })}
                                />
                                <SelectField
                                    label="Sector"
                                    emptyLabel="Todos"
                                    value={form.sector_id}
                                    options={opciones.sectores}
                                    onChange={(v) => setForm({ ...form, sector_id: v })}
                                />
                                <SelectField
                                    label="Sexo"
                                    emptyLabel="Ambos"
                                    value={form.genero}
                                    options={GENEROS}
                                    onChange={(v) => setForm({ ...form, genero: v })}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Profesión, sector y sexo sólo aplican a individuos.
                            </p>
                        </div>
                    )}
                </form>

                {/* Pestañas y selector de vista */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2">
                        {PESTANAS.map(({ clave, titulo, icono: Icono }) => (
                            <Button
                                key={clave}
                                size="sm"
                                variant={clave === activa ? 'default' : 'outline'}
                                onClick={() => setActiva(clave)}
                            >
                                <Icono className="mr-2 size-4" />
                                {titulo}
                                {buscado && (
                                    <span className="ml-2 rounded-full bg-background/20 px-1.5 text-xs tabular-nums">
                                        {paginacion[clave]?.total ?? 0}
                                    </span>
                                )}
                            </Button>
                        ))}
                    </div>

                    <div className="flex gap-1 rounded-lg border p-0.5">
                        {(
                            [
                                ['tarjetas', 'Tarjetas', LayoutGrid],
                                ['tabla', 'Tabla', Rows3],
                            ] as const
                        ).map(([clave, etiqueta, Icono]) => (
                            <Button
                                key={clave}
                                size="sm"
                                variant={vista === clave ? 'secondary' : 'ghost'}
                                onClick={() => cambiarVista(clave)}
                                aria-pressed={vista === clave}
                            >
                                <Icono className="mr-2 size-4" />
                                {etiqueta}
                            </Button>
                        ))}
                    </div>
                </div>

                {!buscado ? (
                    <Vacio
                        titulo="Empieza por buscar"
                        texto="Escribe un nombre, un alias o un RFC, o abre Más filtros para acotar por ubicación."
                    />
                ) : lista.length === 0 ? (
                    <Vacio
                        titulo={`Sin resultados en ${pestanaActiva.titulo.toLowerCase()}`}
                        texto="Revisa las casillas de Buscar en, o prueba en otra pestaña."
                    />
                ) : (
                    <>
                        {vista === 'tarjetas' ? (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {lista.map((ficha) => (
                                    <Tarjeta
                                        key={`${ficha.tipo}-${ficha.id}`}
                                        ficha={ficha}
                                        onVer={() => setVer(ficha)}
                                        onEditar={() =>
                                            setEditar({ tipo: activa, id: ficha.id })
                                        }
                                        onComentarios={() => setComentarios(ficha)}
                                        onSolicitudes={
                                            SOLICITANTE[activa]
                                                ? () => abrirSolicitudes(ficha)
                                                : null
                                        }
                                        onEliminar={() => setEliminar(ficha)}
                                    />
                                ))}
                            </div>
                        ) : (
                            <Tabla
                                pestana={activa}
                                fichas={lista}
                                onVer={setVer}
                                onEditar={(ficha) =>
                                    setEditar({ tipo: activa, id: ficha.id })
                                }
                                onComentarios={setComentarios}
                                onSolicitudes={
                                    SOLICITANTE[activa] ? abrirSolicitudes : null
                                }
                                onEliminar={setEliminar}
                            />
                        )}

                        {paginas && paginas.lastPage > 1 && (
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span>
                                    {paginas.total} registros · página{' '}
                                    {paginas.currentPage} de {paginas.lastPage}
                                </span>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={!paginas.prevUrl}
                                        onClick={() =>
                                            paginas.prevUrl &&
                                            router.visit(paginas.prevUrl, {
                                                preserveState: true,
                                            })
                                        }
                                    >
                                        Anterior
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={!paginas.nextUrl}
                                        onClick={() =>
                                            paginas.nextUrl &&
                                            router.visit(paginas.nextUrl, {
                                                preserveState: true,
                                            })
                                        }
                                    >
                                        Siguiente
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            {editar?.tipo === 'individuos' && (
                <BeneficiarioFormDialog
                    id={editar.id}
                    opciones={opciones}
                    onClose={() => setEditar(null)}
                />
            )}
            {editar?.tipo === 'organizaciones' && (
                <OrganizacionFormDialog
                    id={editar.id}
                    opciones={opciones}
                    onClose={() => setEditar(null)}
                />
            )}
            {editar?.tipo === 'proveedores' && (
                <ProveedorFormDialog
                    id={editar.id}
                    opciones={opciones}
                    onClose={() => setEditar(null)}
                />
            )}

            {ver && <FichaDialog ficha={ver} onClose={() => setVer(null)} />}

            {comentarios && (
                <ComentariosDialog
                    tipo={comentarios.tipo}
                    id={comentarios.id}
                    nombre={comentarios.titulo}
                    onClose={() => setComentarios(null)}
                />
            )}

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar del directorio"
                entityLabel="el registro"
                itemName={eliminar?.titulo}
                onConfirm={() => {
                    if (!eliminar) {
                        return;
                    }

                    router.delete(RUTA_ELIMINAR[eliminar.tipo].url(eliminar.id), {
                        preserveScroll: true,
                        onSuccess: () => setEliminar(null),
                        onError: (errores) =>
                            toast.error(
                                resolveFormErrorMessage(errores, 'No se pudo eliminar.'),
                            ),
                    });
                }}
            />
        </>
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

DirectorioIndex.layout = {
    breadcrumbs: [{ title: 'Directorio', href: index() }],
};
