import {
    CalendarDays,
    IdCard,
    MapPin,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'lucide-react';
import { Monograma } from '@/components/monograma';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { etiquetaSexo } from './sexo';

type Detenido = Record<string, string | number | boolean | null>;

/**
 * Tarjeta del detenido, con el mismo lenguaje que las del Directorio: la foto
 * o el monograma abren la ficha, y debajo va lo que identifica la retención
 * (motivo, lugar y fecha), que es lo que se busca al hojear el listado.
 */
export function TarjetaDetenido({
    detenido,
    onFicha,
    onEditar,
    onEliminar,
}: {
    detenido: Detenido;
    onFicha: () => void;
    onEditar: () => void;
    onEliminar: () => void;
}) {
    const nombre = String(detenido.nombre_completo ?? 'Sin nombre');
    // Alias y edad casi siempre son lo poco que distingue un registro de otro.
    const meta = [
        detenido.alias ? `"${detenido.alias}"` : null,
        detenido.edad ? `${detenido.edad} años` : null,
        etiquetaSexo(detenido.sexo),
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <div className="flex flex-col rounded-xl border border-sidebar-border/70 bg-card transition-colors hover:border-sidebar-border">
            <div className="flex items-start gap-3 p-4">
                <button
                    type="button"
                    onClick={onFicha}
                    title="Ver ficha"
                    className="rounded-lg focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Monograma
                        nombre={nombre}
                        fotoUrl={
                            detenido.tiene_foto
                                ? `/directorio/detenidos/${detenido.id}/foto.img`
                                : null
                        }
                    />
                </button>

                <div className="min-w-0 flex-1">
                    <button
                        type="button"
                        onClick={onFicha}
                        className="block max-w-full truncate text-left text-[15px] font-semibold tracking-tight hover:underline"
                        title={nombre}
                    >
                        {nombre}
                    </button>
                    {meta && (
                        <p className="truncate text-xs text-muted-foreground">
                            {meta}
                        </p>
                    )}
                    <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                        <MapPin className="size-3 shrink-0" />
                        <span className="truncate">
                            {String(detenido.lugar_retencion ?? 'Sin lugar de retención')}
                        </span>
                    </p>
                    {detenido.fecha_retencion && (
                        <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <CalendarDays className="size-3 shrink-0" />
                            <span className="tabular-nums">
                                {String(detenido.fecha_retencion)}
                            </span>
                        </p>
                    )}
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 shrink-0"
                        >
                            <MoreHorizontal className="size-4" />
                            <span className="sr-only">Acciones</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onClick={onFicha}>
                            <IdCard className="mr-2 size-4" /> Ver ficha
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={onEditar}>
                            <Pencil className="mr-2 size-4" /> Editar
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onClick={onEliminar}
                        >
                            <Trash2 className="mr-2 size-4" /> Eliminar
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div className="mt-auto border-t px-4 py-2.5">
                <p className="line-clamp-2 text-xs text-muted-foreground">
                    {detenido.motivo_retencion
                        ? String(detenido.motivo_retencion)
                        : 'Sin motivo capturado'}
                </p>
            </div>
        </div>
    );
}
