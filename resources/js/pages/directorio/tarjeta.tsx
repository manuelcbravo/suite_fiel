import {
    ClipboardList,
    Mail,
    MapPin,
    MessageSquare,
    MoreHorizontal,
    Pencil,
    Phone,
    Smartphone,
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
import type { Ficha } from './types';

/**
 * Tarjeta del directorio legacy: identidad, ubicación y cómo contactar.
 * El nombre y el monograma abren la ficha, igual que la foto en el original.
 */
export function Tarjeta({
    ficha,
    onVer,
    onEditar,
    onComentarios,
    onSolicitudes,
    onEliminar,
}: {
    ficha: Ficha;
    onVer: () => void;
    onEditar: () => void;
    onComentarios: () => void;
    /** Sólo para individuos y organizaciones; los proveedores no solicitan. */
    onSolicitudes: (() => void) | null;
    onEliminar: () => void;
}) {
    // Alias y ocupación casi siempre vienen vacíos: se juntan en un renglón
    // para que un registro escaso no deje dos huecos en la tarjeta.
    const meta = [ficha.subtitulo, ficha.etiqueta].filter(Boolean).join(' · ');
    const sinContacto = !ficha.celular && !ficha.telefono && !ficha.correo;

    return (
        <div className="flex flex-col rounded-xl border border-sidebar-border/70 bg-card transition-colors hover:border-sidebar-border">
            <div className="flex items-start gap-3 p-4">
                <button
                    type="button"
                    onClick={onVer}
                    title="Ver ficha"
                    className="rounded-lg focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Monograma
                        nombre={ficha.titulo}
                    fotoUrl={
                        ficha.tiene_foto
                            ? `/directorio/${ficha.tipo}/${ficha.id}/foto.img`
                            : null
                    }
                    />
                </button>

                <div className="min-w-0 flex-1">
                    <button
                        type="button"
                        onClick={onVer}
                        className="block max-w-full truncate text-left text-[15px] font-semibold tracking-tight hover:underline"
                        title={ficha.titulo}
                    >
                        {ficha.titulo}
                    </button>
                    {meta && (
                        <p className="truncate text-xs text-muted-foreground">
                            {meta}
                        </p>
                    )}
                    <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                        <MapPin className="size-3 shrink-0" />
                        <span className="truncate">
                            {ficha.ubicacion ?? 'Sin ubicación'}
                        </span>
                    </p>
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
                        <DropdownMenuItem onClick={onVer}>
                            <User className="mr-2 size-4" /> Ver ficha
                        </DropdownMenuItem>
                        <DropdownMenuItem onClick={onEditar}>
                            <Pencil className="mr-2 size-4" /> Editar
                        </DropdownMenuItem>
                        {onSolicitudes && (
                            <DropdownMenuItem onClick={onSolicitudes}>
                                <ClipboardList className="mr-2 size-4" />{' '}
                                Solicitudes
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem onClick={onComentarios}>
                            <MessageSquare className="mr-2 size-4" /> Comentarios
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

            {/* Pie de contacto: lo que se usa para actuar sobre el registro. */}
            <div className="mt-auto flex flex-wrap items-center gap-1.5 border-t px-4 py-2.5">
                {sinContacto ? (
                    <span className="text-xs text-muted-foreground">
                        Sin teléfono ni correo
                    </span>
                ) : (
                    <>
                        <Contacto
                            icono={<Smartphone className="size-3.5" />}
                            href={ficha.celular ? `tel:${ficha.celular}` : null}
                        >
                            {ficha.celular}
                        </Contacto>
                        <Contacto
                            icono={<Phone className="size-3.5" />}
                            href={
                                ficha.telefono ? `tel:${ficha.telefono}` : null
                            }
                        >
                            {ficha.telefono}
                        </Contacto>
                        <Contacto
                            icono={<Mail className="size-3.5" />}
                            href={ficha.correo ? `mailto:${ficha.correo}` : null}
                        >
                            {ficha.correo}
                        </Contacto>
                    </>
                )}
            </div>
        </div>
    );
}

/** Dato de contacto accionable; se omite si no hay valor. */
function Contacto({
    icono,
    href,
    children,
}: {
    icono: React.ReactNode;
    href: string | null;
    children: React.ReactNode;
}) {
    if (!children || !href) {
        return null;
    }

    return (
        <a
            href={href}
            className="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        >
            <span className="shrink-0">{icono}</span>
            <span className="truncate">{children}</span>
        </a>
    );
}
