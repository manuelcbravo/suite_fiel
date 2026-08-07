import {
    ClipboardList,
    Mail,
    MessageSquare,
    MoreHorizontal,
    Pencil,
    Trash2,
    User,
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
import type { Ficha, Pestana } from './types';

/**
 * Encabezados por pestaña: los datos que viajan son los mismos, sólo cambia
 * qué significa cada columna.
 */
const COLUMNAS: Record<Pestana, [string, string, string]> = {
    individuos: ['Nombre', 'Alias', 'Ocupación'],
    organizaciones: ['Organización', 'Tipo', 'Representante'],
    proveedores: ['Proveedor', 'Especialidad', 'RFC'],
};

/** Vista de tabla del directorio, alterna con la de tarjetas. */
export function Tabla({
    pestana,
    fichas,
    onVer,
    onEditar,
    onComentarios,
    onSolicitudes,
    onEliminar,
}: {
    pestana: Pestana;
    fichas: Ficha[];
    onVer: (ficha: Ficha) => void;
    onEditar: (ficha: Ficha) => void;
    onComentarios: (ficha: Ficha) => void;
    /** Sólo para individuos y organizaciones; los proveedores no solicitan. */
    onSolicitudes: ((ficha: Ficha) => void) | null;
    onEliminar: (ficha: Ficha) => void;
}) {
    const [nombre, subtitulo, etiqueta] = COLUMNAS[pestana];

    return (
        <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-card">
            <table className="w-full min-w-[900px] border-collapse text-sm">
                <thead>
                    <tr className="bg-muted/40 text-left">
                        {[nombre, subtitulo, etiqueta, 'Ubicación', 'Contacto', ''].map(
                            (titulo, i) => (
                                <th
                                    key={i}
                                    className="border-b px-3 py-2.5 text-[11px] font-medium tracking-wider whitespace-nowrap text-muted-foreground uppercase"
                                >
                                    {titulo}
                                </th>
                            ),
                        )}
                    </tr>
                </thead>
                <tbody>
                    {fichas.map((ficha) => (
                        <tr
                            key={`${ficha.tipo}-${ficha.id}`}
                            className="transition-colors hover:bg-muted/30"
                        >
                            <td className="border-b px-3 py-2.5">
                                <button
                                    type="button"
                                    onClick={() => onVer(ficha)}
                                    className="flex items-center gap-2.5 text-left font-medium hover:underline"
                                >
                                    <Monograma
                                        nombre={ficha.titulo}
                    fotoUrl={
                        ficha.tiene_foto
                            ? `/directorio/${ficha.tipo}/${ficha.id}/foto.img`
                            : null
                    }
                                        tamano="sm"
                                    />
                                    {ficha.titulo}
                                </button>
                            </td>
                            <td className="border-b px-3 py-2.5 text-muted-foreground">
                                {ficha.subtitulo ?? '—'}
                            </td>
                            <td className="border-b px-3 py-2.5 text-muted-foreground">
                                {ficha.etiqueta ?? '—'}
                            </td>
                            <td className="border-b px-3 py-2.5 text-muted-foreground">
                                {ficha.ubicacion ?? '—'}
                            </td>
                            <td className="border-b px-3 py-2.5">
                                <div className="flex flex-col gap-0.5">
                                    {ficha.celular && (
                                        <a
                                            href={`tel:${ficha.celular}`}
                                            className="tabular-nums hover:underline"
                                        >
                                            {ficha.celular}
                                        </a>
                                    )}
                                    {ficha.correo && (
                                        <a
                                            href={`mailto:${ficha.correo}`}
                                            className="flex items-center gap-1.5 text-muted-foreground hover:underline"
                                        >
                                            <Mail className="size-3.5 shrink-0" />
                                            {ficha.correo}
                                        </a>
                                    )}
                                    {!ficha.celular && !ficha.correo && (
                                        <span className="text-muted-foreground">—</span>
                                    )}
                                </div>
                            </td>
                            <td className="border-b px-3 py-2.5">
                                <div className="flex justify-end">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                            >
                                                <MoreHorizontal className="size-4" />
                                                <span className="sr-only">Acciones</span>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem onClick={() => onVer(ficha)}>
                                                <User className="mr-2 size-4" /> Ver ficha
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() => onEditar(ficha)}
                                            >
                                                <Pencil className="mr-2 size-4" /> Editar
                                            </DropdownMenuItem>
                                            {onSolicitudes && (
                                                <DropdownMenuItem
                                                    onClick={() => onSolicitudes(ficha)}
                                                >
                                                    <ClipboardList className="mr-2 size-4" />{' '}
                                                    Solicitudes
                                                </DropdownMenuItem>
                                            )}
                                            <DropdownMenuItem
                                                onClick={() => onComentarios(ficha)}
                                            >
                                                <MessageSquare className="mr-2 size-4" />{' '}
                                                Comentarios
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onClick={() => onEliminar(ficha)}
                                            >
                                                <Trash2 className="mr-2 size-4" /> Eliminar
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
