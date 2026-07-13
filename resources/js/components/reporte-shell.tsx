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
type Valor = string | number | boolean | null | number[];

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

export function ReporteShell({
    title,
    icon: Icon,
    pageUrl,
    exportBase,
    params,
    onLimpiar,
    columns,
    filas,
    paginacion,
    children,
}: {
    title: string;
    icon: LucideIcon;
    pageUrl: string;
    exportBase: string;
    params: Record<string, Valor>;
    onLimpiar: () => void;
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
        url && router.get(url, {}, { preserveState: true, preserveScroll: true });

    return (
        <div className="space-y-4 p-4">
            <div className="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                <Icon className="size-5 text-primary" />
                <div>
                    <h1 className="text-xl font-semibold">{title}</h1>
                    <p className="text-sm text-muted-foreground">
                        Filtra y exporta a Excel.
                    </p>
                </div>
            </div>

            <div className="space-y-4 rounded-xl border border-sidebar-border/70 bg-card p-4">
                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    {children}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button onClick={aplicar}>
                        <Filter className="mr-2 size-4" /> Aplicar filtros
                    </Button>
                    <Button variant="outline" onClick={onLimpiar}>
                        <RotateCcw className="mr-2 size-4" /> Limpiar
                    </Button>
                    <Button
                        asChild
                        className="ml-auto gap-2 border-0 bg-gradient-to-b from-emerald-500 to-emerald-600 text-white shadow-sm transition hover:from-emerald-500 hover:to-emerald-700 hover:shadow-md dark:from-emerald-500 dark:to-emerald-600"
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
                                <TableHead key={c.key}>{c.header}</TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filas.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center text-muted-foreground"
                                >
                                    Sin resultados.
                                </TableCell>
                            </TableRow>
                        ) : (
                            filas.map((fila, i) => (
                                <TableRow key={i}>
                                    {columns.map((c) => (
                                        <TableCell key={c.key}>
                                            {fila[c.key] ?? '—'}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex items-center justify-end gap-2">
                <span className="text-xs text-muted-foreground">
                    {paginacion.total} resultados · página {paginacion.currentPage}{' '}
                    de {paginacion.lastPage}
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
