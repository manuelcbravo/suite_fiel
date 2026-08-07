import { CrudFormDialog } from '@/components/crud-form-dialog';
import { etiquetaSexo } from './sexo';

type Detenido = Record<string, string | number | boolean | null>;

/**
 * Ficha del detenido (legacy `ficha_dtnd.php`): sólo lectura, agrupada como
 * en el original — identidad, contacto y datos de la retención.
 */
export function FichaDetenidoDialog({
    detenido,
    onClose,
}: {
    detenido: Detenido;
    onClose: () => void;
}) {
    const bloques: { titulo: string; datos: [string, unknown][] }[] = [
        {
            titulo: 'Identidad',
            datos: [
                ['Alias / Apodo', detenido.alias],
                ['Edad', detenido.edad],
                ['Fecha de nacimiento', detenido.fecha_nac],
                ['Sexo', etiquetaSexo(detenido.sexo)],
                ['Nacionalidad', detenido.nacionalidad],
                ['Lugar de nacimiento', detenido.lugar_nac],
                ['Ocupación / Oficio', detenido.ocupacion_nombre],
                ['Padre', detenido.padre_nombre],
                ['Madre', detenido.madre_nombre],
            ],
        },
        {
            titulo: 'Contacto',
            datos: [
                ['Dirección', detenido.direccion],
                ['Celular', detenido.celular],
                ['Teléfono', detenido.telefono],
            ],
        },
        {
            titulo: 'Retención',
            datos: [
                ['Lugar', detenido.lugar_retencion],
                ['Fecha', detenido.fecha_retencion],
                ['Motivo', detenido.motivo_retencion],
                ['Observaciones', detenido.observaciones],
                ['Denuncia', detenido.denuncia_label],
            ],
        },
    ];

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="lg"
            showFooter={false}
            title={String(detenido.nombre_completo ?? 'Detenido')}
            description="Ficha del detenido"
        >
            {bloques.map(({ titulo, datos }) => {
                // El legacy omite los campos vacíos de la ficha.
                const visibles = datos.filter(
                    ([, valor]) => valor !== null && valor !== '' && valor !== undefined,
                );

                if (visibles.length === 0) {
                    return null;
                }

                return (
                    <div key={titulo} className="space-y-3 border-t pt-4 first:border-t-0 first:pt-0">
                        <h3 className="text-sm font-semibold text-muted-foreground">
                            {titulo}
                        </h3>
                        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            {visibles.map(([etiqueta, valor]) => (
                                <div key={etiqueta}>
                                    <dt className="text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                        {etiqueta}
                                    </dt>
                                    <dd className="text-sm break-words">
                                        {String(valor)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                );
            })}
        </CrudFormDialog>
    );
}
