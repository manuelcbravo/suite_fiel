import { router } from '@inertiajs/react';
import type React from 'react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type CellValue = string | number | null | undefined;

export type DataTableColumn<TData> = {
    key: string;
    header: string;
    cell: (row: TData) => React.ReactNode;
    accessor?: (row: TData) => CellValue;
    className?: string;
};

/**
 * Estado del paginador de Laravel para el modo servidor: la búsqueda va
 * por query string (con debounce al escribir) y la paginación navega con
 * los enlaces del paginador.
 */
export type DataTableServer = {
    total: number;
    currentPage: number;
    lastPage: number;
    prevUrl: string | null;
    nextUrl: string | null;
    busqueda: string;
};

type DataTableProps<TData> = {
    columns: DataTableColumn<TData>[];
    data: TData[];
    emptyMessage?: string;
    searchPlaceholder?: string;
    searchColumn?: string;
    pageSize?: number;
    server?: DataTableServer;
    /** Filtros adicionales que la búsqueda debe preservar (modo servidor). */
    extraParams?: Record<string, string | number | null | undefined>;
};

export function DataTable<TData>({
    columns,
    data,
    emptyMessage = 'No hay resultados.',
    searchPlaceholder = 'Buscar...',
    searchColumn,
    pageSize = 10,
    server,
    extraParams,
}: DataTableProps<TData>) {
    const [search, setSearch] = useState(server?.busqueda ?? '');
    const [page, setPage] = useState(1);
    const extraParamsKey = JSON.stringify(extraParams ?? {});

    // Modo servidor: al escribir se consulta por query con debounce.
    useEffect(() => {
        if (!server || search === server.busqueda) {
            return;
        }

        const timer = setTimeout(() => {
            const params: Record<string, string | number> = {};

            for (const [clave, valor] of Object.entries(
                JSON.parse(extraParamsKey) as Record<string, unknown>,
            )) {
                if (valor !== null && valor !== undefined && valor !== '') {
                    params[clave] = valor as string | number;
                }
            }

            if (search !== '') {
                params.busqueda = search;
            }

            router.get(window.location.pathname, params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);

        return () => clearTimeout(timer);
    }, [search, server, extraParamsKey]);

    const filteredData = useMemo(() => {
        if (server || !searchColumn || search.trim().length === 0) {
            return data;
        }

        const searchValue = search.trim().toLowerCase();
        const column = columns.find((item) => item.key === searchColumn);

        if (!column) {
            return data;
        }

        return data.filter((row) => {
            const accessor = column.accessor?.(row);
            const fallback =
                typeof accessor === 'string' || typeof accessor === 'number'
                    ? String(accessor)
                    : null;

            return (fallback ?? '').toLowerCase().includes(searchValue);
        });
    }, [columns, data, search, searchColumn, server]);

    const totalPages = server
        ? server.lastPage
        : Math.max(1, Math.ceil(filteredData.length / pageSize));
    const currentPage = server
        ? server.currentPage
        : Math.min(page, totalPages);
    const start = (currentPage - 1) * pageSize;
    const paginatedData = server
        ? filteredData
        : filteredData.slice(start, start + pageSize);
    const totalResults = server ? server.total : filteredData.length;

    const irA = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
        }
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <Input
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setPage(1);
                    }}
                    placeholder={searchPlaceholder}
                    className="w-full sm:max-w-sm"
                />
                <p className="text-xs text-muted-foreground">
                    {totalResults} resultados
                </p>
            </div>

            <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={column.className}
                                >
                                    {column.header}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {paginatedData.length > 0 ? (
                            paginatedData.map((row, index) => (
                                <TableRow key={index}>
                                    {columns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={column.className}
                                        >
                                            {column.cell(row)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex items-center justify-end gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        server
                            ? irA(server.prevUrl)
                            : setPage((value) => Math.max(1, value - 1))
                    }
                    disabled={currentPage <= 1}
                >
                    Anterior
                </Button>
                <span className="text-xs text-muted-foreground">
                    Página {currentPage} de {totalPages}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        server
                            ? irA(server.nextUrl)
                            : setPage((value) =>
                                  Math.min(totalPages, value + 1),
                              )
                    }
                    disabled={currentPage >= totalPages}
                >
                    Siguiente
                </Button>
            </div>
        </div>
    );
}
