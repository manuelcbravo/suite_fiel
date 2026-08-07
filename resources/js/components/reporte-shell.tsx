import { router } from '@inertiajs/react';
import { Filter, RotateCcw, Sheet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type Columna = { key: string; header: string };
export type Paginacion = {
    total: number;
    currentPage: number;
    lastPage: number;
    prevUrl: string | null;
    nextUrl: string | null;
};
type Valor = string | number | boolean | null | number[] | string[];

const nf = new Intl.NumberFormat('es-MX');

/** Serializa filtros a query string (ignora vacíos, expande arreglos). */
export function toQuery(params: Record<string, Valor>): Record<string, string> {
    const out: Record<string, string> = {};

    for (const [k, v] of Object.entries(params)) {
        if (v === null || v === '' || v === undefined) {
            continue;
        }

        if (Array.isArray(v)) {
            if (v.length > 0) {
                out[k] = v.join(',');
            }
        } else {
            out[k] = String(v);
        }
    }

    return out;
}

/**
 * Banda con rótulo, para separar los dos actos que el legacy tenía en paneles
 * distintos: qué se busca y qué columnas se muestran.
 */
function Banda({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <div className="border-b border-sidebar-border/70 p-4 last:border-b-0">
            <h2 className="mb-3 flex items-center gap-3 text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                <span className="whitespace-nowrap">{titulo}</span>
                <span className="h-px flex-1 bg-border" />
            </h2>
            {children}
        </div>
    );
}

export function ReporteShell({
    title,
    descripcion,
    sustantivo,
    icon: Icon,
    pageUrl,
    exportBase,
    params,
    onLimpiar,
    pestanas,
    onPestana,
    columnasField,
    totalColumnas,
    columns,
    filas,
    paginacion,
    children,
}: {
    title: string;
    /** Qué contesta este reporte, en una línea. */
    descripcion: string;
    /** Cómo se llama lo que se cuenta: "eventos", "solicitudes"… */
    sustantivo: string;
    icon: LucideIcon;
    pageUrl: string;
    exportBase: string;
    params: Record<string, Valor>;
    onLimpiar: () => void;
    /** Pestañas de la vista, cuando el reporte tiene varias (Gestión). */
    pestanas?: { clave: string; label: string; activa: boolean }[];
    onPestana?: (clave: string) => void;
    /** Las casillas de columnas; van en su propia banda. */
    columnasField?: ReactNode;
    totalColumnas?: number;
    columns: Columna[];
    filas: Record<string, string | number | null>[];
    paginacion: Paginacion;
    children: ReactNode;
}) {
    const limpios = toQuery(params);
    const exportUrl = `${exportBase}?${new URLSearchParams(limpios).toString()}`;

    const aplicar = () =>
        router.get(pageUrl, limpios, {
            preserveState: true,
            preserveScroll: true,
        });

    const ir = (url: string | null) =>
        url &&
        router.get(url, {}, { preserveState: true, preserveScroll: true });

    return (
        <div className="space-y-4 p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <h1 className="flex items-center gap-2 text-xl font-semibold tracking-tight">
                    <Icon className="size-5 text-muted-foreground" />
                    {title}
                </h1>
                <p className="text-sm text-muted-foreground">{descripcion}</p>
            </div>

            {pestanas && pestanas.length > 0 && (
                <div className="flex flex-wrap gap-1 rounded-xl border border-sidebar-border/70 bg-card p-1">
                    {pestanas.map((p) => (
                        <button
                            key={p.clave}
                            type="button"
                            aria-pressed={p.activa}
                            className={`rounded-lg px-3 py-1.5 text-sm transition-colors ${
                                p.activa
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent'
                            }`}
                            onClick={() => onPestana?.(p.clave)}
                        >
                            {p.label}
                        </button>
                    ))}
                </div>
            )}

            <div className="rounded-xl border border-sidebar-border/70 bg-card">
                <Banda titulo="Qué se busca">
                    <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {children}
                    </div>
                </Banda>

                {columnasField && (
                    <Banda titulo="Qué columnas se muestran">
                        {columnasField}
                    </Banda>
                )}

                {/* Lo que se va a producir, dicho en palabras, justo donde se
                    decide descargarlo. */}
                <div className="flex flex-wrap items-center gap-x-4 gap-y-3 border-t border-sidebar-border/70 p-4">
                    <Button onClick={aplicar}>
                        <Filter className="mr-2 size-4" /> Aplicar filtros
                    </Button>
                    <Button variant="outline" onClick={onLimpiar}>
                        <RotateCcw className="mr-2 size-4" /> Limpiar
                    </Button>

                    <p className="order-last w-full text-sm text-muted-foreground sm:order-none sm:ml-auto sm:w-auto sm:text-right">
                        Se descargan{' '}
                        <span className="font-medium text-foreground tabular-nums">
                            {nf.format(paginacion.total)} {sustantivo}
                        </span>
                        {totalColumnas ? (
                            <>
                                {' '}
                                con{' '}
                                <span className="font-medium text-foreground tabular-nums">
                                    {columns.length} de {totalColumnas} columnas
                                </span>
                            </>
                        ) : null}
                        .
                    </p>

                    <Button
                        asChild
                        className="gap-2 border-0 bg-emerald-600 text-white hover:bg-emerald-700"
                    >
                        <a href={exportUrl} download>
                            <span className="grid size-5 place-items-center rounded bg-white/20">
                                <Sheet className="size-3.5" />
                            </span>
                            Descargar Excel
                        </a>
                    </Button>
                </div>
            </div>

            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((c) => (
                                <TableHead
                                    key={c.key}
                                    className="whitespace-nowrap"
                                >
                                    {c.header}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filas.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={Math.max(columns.length, 1)}
                                    className="h-28 text-center text-sm text-muted-foreground"
                                >
                                    No hay {sustantivo} que coincidan con estos
                                    filtros. Quita alguno o amplía el rango de
                                    fechas.
                                </TableCell>
                            </TableRow>
                        ) : (
                            filas.map((fila, i) => (
                                <TableRow key={i}>
                                    {columns.map((c) => (
                                        <TableCell
                                            key={c.key}
                                            // Un texto largo no debe estirar la
                                            // tabla hasta sacarla de pantalla.
                                            className="max-w-[28rem] align-top whitespace-normal"
                                        >
                                            {fila[c.key] ?? '—'}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex items-center justify-end gap-3">
                <span className="text-xs text-muted-foreground tabular-nums">
                    Página {paginacion.currentPage} de {paginacion.lastPage}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!paginacion.prevUrl}
                    onClick={() => ir(paginacion.prevUrl)}
                >
                    Anterior
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!paginacion.nextUrl}
                    onClick={() => ir(paginacion.nextUrl)}
                >
                    Siguiente
                </Button>
            </div>
        </div>
    );
}
