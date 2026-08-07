import { Mail, Phone } from 'lucide-react';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { Monograma } from '@/components/monograma';
import type { Ficha } from './types';

/**
 * Ficha de detalle del legacy (`info_contacto.php`, `info_grupo.php`,
 * `info_prov.php`): sólo lectura, con los datos que no caben en la tarjeta.
 */
export function FichaDialog({
    ficha,
    onClose,
}: {
    ficha: Ficha;
    onClose: () => void;
}) {
    // El legacy deja los campos vacíos fuera de la ficha.
    const datos = Object.entries(ficha.datos).filter(
        ([, valor]) => valor !== null && valor !== '',
    );

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="lg"
            showFooter={false}
            title={ficha.titulo}
            description={ficha.subtitulo ?? ''}
        >
            <div className="flex items-start gap-4">
                <Monograma
                    nombre={ficha.titulo}
                    fotoUrl={
                        ficha.tiene_foto
                            ? `/directorio/${ficha.tipo}/${ficha.id}/foto.img`
                            : null
                    }
                    tamano="lg"
                />

                <div className="min-w-0 flex-1 space-y-1 text-sm">
                    {ficha.etiqueta && (
                        <p className="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                            {ficha.etiqueta}
                        </p>
                    )}
                    {ficha.ubicacion && (
                        <p className="text-muted-foreground">{ficha.ubicacion}</p>
                    )}
                    <div className="flex flex-wrap gap-x-4 gap-y-1">
                        {ficha.celular && (
                            <a
                                href={`tel:${ficha.celular}`}
                                className="flex items-center gap-1.5 hover:underline"
                            >
                                <Phone className="size-3.5" /> {ficha.celular}
                            </a>
                        )}
                        {ficha.correo && (
                            <a
                                href={`mailto:${ficha.correo}`}
                                className="flex items-center gap-1.5 hover:underline"
                            >
                                <Mail className="size-3.5" /> {ficha.correo}
                            </a>
                        )}
                    </div>
                </div>
            </div>

            {datos.length === 0 ? (
                <p className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Este registro sólo tiene los datos de contacto de arriba.
                </p>
            ) : (
                <dl className="grid gap-x-6 gap-y-3 border-t pt-4 sm:grid-cols-2">
                    {datos.map(([etiqueta, valor]) => (
                        <div key={etiqueta}>
                            <dt className="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                {etiqueta}
                            </dt>
                            <dd className="text-sm break-words">{valor}</dd>
                        </div>
                    ))}
                </dl>
            )}
        </CrudFormDialog>
    );
}
