import { Head, router, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    MoreHorizontal,
    Plus,
    ReceiptText,
    Search,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { Encabezado } from '@/components/pagina';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { destroy, index as obraIndex } from '@/routes/obra';
import { EstimacionesDialog } from './estimaciones-dialog';
import { EtapaDialog } from './etapa-dialog';
import type { EtapaOpcion } from './etapas';
import { RielEtapas } from './etapas';
import { FichaDialog } from './ficha-dialog';
import type { Opciones } from './types';

type Fila = {
    id: number;
    nombre: string | null;
    num_obra: string | null;
    localidad: string | null;
    folio: string | null;
    solicitud_id: number | null;
    con_modalidad: boolean;
    etapas: Record<string, boolean>;
};
type Filtros = { q: string; etapa: string | null; pendiente: string | null };
type Paginacion = {
    total: number;
    currentPage: number;
    lastPage: number;
    prevUrl: string | null;
    nextUrl: string | null;
};

export default function ObraIndex({
    obras,
    etapas,
    filtros,
    opciones,
    paginacion,
}: {
    obras: Fila[];
    etapas: EtapaOpcion[];
    filtros: Filtros;
    opciones: Opciones;
    paginacion: Paginacion;
}) {
    const [q, setQ] = useState(filtros.q);
    const [etapa, setEtapa] = useState<string | null>(filtros.etapa);
    const [pendiente, setPendiente] = useState<string | null>(
        filtros.pendiente,
    );
    const [editando, setEditando] = useState<number | 'nueva' | null>(null);
    const [eliminar, setEliminar] = useState<Fila | null>(null);
    const [etapaAbierta, setEtapaAbierta] = useState<{
        obra: number;
        etapa: string;
    } | null>(null);
    const [estimaciones, setEstimaciones] = useState<number | null>(null);
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error, flash?.success]);

    const buscar = () =>
        router.get(
            obraIndex.url(),
            { q, etapa, pendiente },
            { preserveState: true, preserveScroll: true },
        );

    const ir = (url: string | null) =>
        url &&
        router.get(url, {}, { preserveState: true, preserveScroll: true });

    // Los selects usan ids numéricos; aquí las etapas son claves de texto.
    const comoOpciones = etapas.map((e, i) => ({ id: i + 1, nombre: e.label }));
    const claveDe = (id: number | null) =>
        id === null ? null : (etapas[id - 1]?.clave ?? null);
    const idDe = (clave: string | null) => {
        const i = etapas.findIndex((e) => e.clave === clave);

        return i === -1 ? null : i + 1;
    };

    return (
        <>
            <Head title="Obra pública" />
            <div className="space-y-4 p-4">
                <Encabezado
                    titulo="Obra pública"
                    resumen={
                        <>
                            <span className="font-medium text-foreground tabular-nums">
                                {paginacion.total}
                            </span>{' '}
                            {paginacion.total === 1 ? 'obra' : 'obras'}
                        </>
                    }
                    acciones={
                        <Button onClick={() => setEditando('nueva')}>
                            <Plus className="mr-2 size-4" /> Importar solicitud
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <Field className="min-w-64 flex-1">
                        <Label htmlFor="q">Qué buscas</Label>
                        <Input
                            id="q"
                            value={q}
                            placeholder="Nombre de la obra, número, folio o localidad"
                            onChange={(e) => setQ(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && buscar()}
                        />
                    </Field>
                    <div className="w-48">
                        <SelectField
                            label="Etapa cerrada"
                            value={idDe(etapa)}
                            options={comoOpciones}
                            onChange={(v) => setEtapa(claveDe(v))}
                        />
                    </div>
                    <div className="w-48">
                        <SelectField
                            label="Etapa pendiente"
                            value={idDe(pendiente)}
                            options={comoOpciones}
                            onChange={(v) => setPendiente(claveDe(v))}
                        />
                    </div>
                    <Button variant="outline" onClick={buscar}>
                        <Search className="mr-2 size-4" /> Buscar
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre de la obra</TableHead>
                                <TableHead className="whitespace-nowrap">
                                    Localidad
                                </TableHead>
                                <TableHead className="whitespace-nowrap">
                                    Folio de recepción
                                </TableHead>
                                <TableHead className="whitespace-nowrap">
                                    Etapas
                                </TableHead>
                                <TableHead className="w-14" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {obras.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="h-28 text-center text-sm text-muted-foreground"
                                    >
                                        No hay obras que coincidan. Cambia la
                                        búsqueda o importa una solicitud.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                obras.map((o) => (
                                    <TableRow key={o.id}>
                                        <TableCell className="max-w-[38rem] align-top whitespace-normal">
                                            <button
                                                type="button"
                                                className="block text-left underline-offset-4 hover:underline"
                                                onClick={() =>
                                                    setEditando(o.id)
                                                }
                                            >
                                                {o.nombre ?? '—'}
                                            </button>
                                            {o.num_obra && (
                                                <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                                                    Obra {o.num_obra}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-56 align-top text-sm whitespace-normal">
                                            {o.localidad ?? '—'}
                                        </TableCell>
                                        <TableCell className="align-top text-sm whitespace-nowrap tabular-nums">
                                            {o.folio ?? '—'}
                                        </TableCell>
                                        <TableCell className="align-top">
                                            <RielEtapas
                                                etapas={etapas}
                                                estado={o.etapas}
                                                conModalidad={o.con_modalidad}
                                                onAbrir={(etapa) =>
                                                    setEtapaAbierta({
                                                        obra: o.id,
                                                        etapa,
                                                    })
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="align-top">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                    >
                                                        <MoreHorizontal className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            setEditando(o.id)
                                                        }
                                                    >
                                                        <ClipboardList className="mr-2 size-4" />{' '}
                                                        Ficha técnica
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            setEstimaciones(
                                                                o.id,
                                                            )
                                                        }
                                                    >
                                                        <ReceiptText className="mr-2 size-4" />{' '}
                                                        Estimaciones
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() =>
                                                            setEliminar(o)
                                                        }
                                                    >
                                                        <Trash2 className="mr-2 size-4" />{' '}
                                                        Eliminar
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
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

            {/* Se vuelve a montar en cada apertura para arrancar limpio. */}
            <FichaDialog
                key={String(editando)}
                obraId={editando}
                opciones={opciones}
                onClose={() => setEditando(null)}
            />

            <EstimacionesDialog
                key={`est-${estimaciones}`}
                obraId={estimaciones}
                onClose={() => setEstimaciones(null)}
            />

            <EtapaDialog
                key={`${etapaAbierta?.obra}-${etapaAbierta?.etapa}`}
                obraId={etapaAbierta?.obra ?? null}
                etapa={etapaAbierta?.etapa ?? null}
                onClose={() => setEtapaAbierta(null)}
            />

            <ConfirmDeleteDialog
                open={eliminar !== null}
                onOpenChange={(open) => !open && setEliminar(null)}
                title="Eliminar obra"
                entityLabel="la obra"
                itemName={eliminar?.nombre?.slice(0, 50) ?? `#${eliminar?.id}`}
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

ObraIndex.layout = {
    breadcrumbs: [{ title: 'Obra pública', href: '/obra' }],
};
