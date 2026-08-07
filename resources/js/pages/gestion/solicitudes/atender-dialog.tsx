import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { MultiCheckField } from '@/components/multi-check-field';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { HistorialSeguimientos } from './historial-seguimientos';
import type { Opciones, Seguimiento, Solicitud } from './types';

type Decision = 'temporal' | 'rapida' | 'resolver';

type Form = {
    decision: Decision;
    respuesta: string;
    apoyo: string;
    cantidad: string;
    unidad_medida_id: number | null;
    monto: string;
    num_bene: string;
    concepto_id: number | null;
    origen_recurso_id: number | null;
    rubros: number[];
    sectores: number[];
    estado_resp_id: number | null;
    municipio_resp_id: number | null;
    localidad_resp_id: number | null;
    folio_resp: string;
    fecha_resp: string;
};

/**
 * Pantalla "Atender/Resolver" del legacy: `seg_turnar.php` cuando la solicitud
 * está TURNADA (1) y `seg_resolver.php` cuando está PARA RESOLVER (3) o
 * ATENDIDA (5). Ambas comparten los mismos campos; sólo cambia el encabezado.
 *
 * Las tres decisiones intercambian el bloque de captura, igual que el
 * `mostrar()` del legacy: sólo "Resolver solicitud" pide el detalle del apoyo.
 */
export function AtenderDialog({
    solicitud,
    opciones,
    onClose,
}: {
    solicitud: Solicitud;
    opciones: Opciones;
    onClose: () => void;
}) {
    const [historial, setHistorial] = useState<Seguimiento[]>([]);

    // "Resolver" no aplica a las solicitudes de control administrativo.
    const puedeResolver = !solicitud.control_administrativo;

    const form = useForm<Form>({
        decision: puedeResolver ? 'temporal' : 'rapida',
        respuesta: '',
        apoyo: solicitud.apoyo ?? '',
        cantidad: solicitud.cantidad ?? '',
        unidad_medida_id: solicitud.unidad_medida_id,
        monto: solicitud.monto ?? '',
        num_bene: solicitud.num_bene ?? '',
        concepto_id: solicitud.concepto_id,
        origen_recurso_id: solicitud.origen_recurso_id,
        rubros: solicitud.rubros ?? [],
        sectores: solicitud.sectores ?? [],
        estado_resp_id: solicitud.estado_resp_id,
        municipio_resp_id: solicitud.municipio_resp_id,
        localidad_resp_id: solicitud.localidad_resp_id,
        folio_resp: solicitud.folio_resp ?? '',
        fecha_resp: solicitud.fecha_resp ?? '',
    });

    useEffect(() => {
        fetch(`/gestion/solicitudes/${solicitud.id}/seguimientos`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => setHistorial(data as Seguimiento[]));
    }, [solicitud.id]);

    const resolviendo = form.data.decision === 'resolver';

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/gestion/solicitudes/${solicitud.id}/atender`, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errores) =>
                toast.error(
                    resolveFormErrorMessage(
                        errores,
                        'Verifica los campos marcados.',
                    ),
                ),
        });
    };

    const ubicacion = (
        <UbicacionSelects
            estados={opciones.estados}
            value={{
                estado_id: form.data.estado_resp_id,
                municipio_id: form.data.municipio_resp_id,
                localidad_id: form.data.localidad_resp_id,
            }}
            municipioNombre={solicitud.municipio_resp_nombre}
            onChange={(patch) =>
                form.setData({
                    ...form.data,
                    ...('estado_id' in patch
                        ? { estado_resp_id: patch.estado_id ?? null }
                        : {}),
                    ...('municipio_id' in patch
                        ? { municipio_resp_id: patch.municipio_id ?? null }
                        : {}),
                    ...('localidad_id' in patch
                        ? { localidad_resp_id: patch.localidad_id ?? null }
                        : {}),
                })
            }
        />
    );

    const opcionesDecision: { valor: Decision; etiqueta: string }[] = [
        { valor: 'temporal', etiqueta: 'Respuesta temporal' },
        { valor: 'rapida', etiqueta: 'Atención rápida' },
        ...(puedeResolver
            ? [{ valor: 'resolver' as Decision, etiqueta: 'Resolver solicitud' }]
            : []),
    ];

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="xl"
            showFooter={false}
            title={`${solicitud.status === 1 ? 'Turnar' : 'Resolver'} — ${solicitud.folio_sistema ?? solicitud.folio ?? `#${solicitud.id}`}`}
            description={solicitud.solicitud ?? ''}
        >
            <div className="space-y-2">
                <p className="text-sm font-semibold">Seguimientos</p>
                <HistorialSeguimientos seguimientos={historial} />
            </div>

            <form className="space-y-4 border-t pt-4" onSubmit={enviar}>
                <div className="flex flex-wrap gap-4">
                    {opcionesDecision.map(({ valor, etiqueta }) => (
                        <label
                            key={valor}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="radio"
                                name="decidir"
                                className="size-4"
                                checked={form.data.decision === valor}
                                onChange={() => form.setData('decision', valor)}
                            />
                            {etiqueta}
                        </label>
                    ))}
                </div>

                {resolviendo ? (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <FormInputField
                                id="atn-cantidad"
                                label="Cantidad"
                                placeholder="CANTIDAD"
                                value={form.data.cantidad}
                                error={form.errors.cantidad}
                                onChange={(e) =>
                                    form.setData('cantidad', e.target.value)
                                }
                            />
                            <SelectField
                                label="Unidad"
                                value={form.data.unidad_medida_id}
                                options={opciones.unidadesMedida}
                                error={form.errors.unidad_medida_id}
                                onChange={(v) =>
                                    form.setData('unidad_medida_id', v)
                                }
                            />
                            <FormInputField
                                id="atn-apoyo"
                                label="Apoyo"
                                placeholder="APOYO"
                                value={form.data.apoyo}
                                error={form.errors.apoyo}
                                onChange={(e) =>
                                    form.setData('apoyo', e.target.value)
                                }
                            />
                            <FormInputField
                                id="atn-monto"
                                label="Inversión aproximada"
                                placeholder="INVERSIÓN APROXIMADA"
                                value={form.data.monto}
                                error={form.errors.monto}
                                onChange={(e) =>
                                    form.setData('monto', e.target.value)
                                }
                            />
                            <FormInputField
                                id="atn-numbene"
                                label="Núm. de beneficiarios"
                                placeholder="NÚM. DE BENEFICIARIOS"
                                value={form.data.num_bene}
                                error={form.errors.num_bene}
                                onChange={(e) =>
                                    form.setData('num_bene', e.target.value)
                                }
                            />
                            <SelectField
                                label="Concepto del apoyo"
                                value={form.data.concepto_id}
                                options={opciones.conceptos}
                                error={form.errors.concepto_id}
                                onChange={(v) => form.setData('concepto_id', v)}
                            />
                            <SelectField
                                label="Origen del recurso"
                                value={form.data.origen_recurso_id}
                                options={opciones.origenesRecurso}
                                error={form.errors.origen_recurso_id}
                                onChange={(v) =>
                                    form.setData('origen_recurso_id', v)
                                }
                            />
                            <FormInputField
                                id="atn-fecha-entrega"
                                label="Fecha de entrega"
                                placeholder="AAAA-MM-DD"
                                value={form.data.fecha_resp}
                                error={form.errors.fecha_resp}
                                onChange={(e) =>
                                    form.setData('fecha_resp', e.target.value)
                                }
                            />
                            <FormInputField
                                id="atn-folio-entrega"
                                label="Folio de entrega"
                                placeholder="FOLIO DE ENTREGA"
                                value={form.data.folio_resp}
                                error={form.errors.folio_resp}
                                onChange={(e) =>
                                    form.setData('folio_resp', e.target.value)
                                }
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <MultiCheckField
                                label="Segmento poblacional"
                                options={opciones.sectores}
                                value={form.data.sectores}
                                error={form.errors.sectores}
                                onChange={(v) => form.setData('sectores', v)}
                            />
                            <MultiCheckField
                                label="Rubro"
                                options={opciones.rubros}
                                value={form.data.rubros}
                                error={form.errors.rubros}
                                onChange={(v) => form.setData('rubros', v)}
                            />
                        </div>

                        <div className="space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">
                                Localidad donde se va a entregar el apoyo
                            </p>
                            {ubicacion}
                        </div>
                    </>
                ) : (
                    <>
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-muted-foreground">
                                Localidad donde se va a entregar el apoyo
                            </p>
                            {ubicacion}
                        </div>

                        {/* En el legacy la fecha y el concepto sólo se piden en
                            la atención rápida, no en la respuesta temporal. */}
                        {form.data.decision === 'rapida' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormInputField
                                    id="atn-fecha-ntrg"
                                    label="Fecha de entrega"
                                    placeholder="AAAA-MM-DD"
                                    value={form.data.fecha_resp}
                                    error={form.errors.fecha_resp}
                                    onChange={(e) =>
                                        form.setData(
                                            'fecha_resp',
                                            e.target.value,
                                        )
                                    }
                                />
                                <SelectField
                                    label="Concepto del apoyo"
                                    value={form.data.concepto_id}
                                    options={opciones.conceptos}
                                    error={form.errors.concepto_id}
                                    onChange={(v) =>
                                        form.setData('concepto_id', v)
                                    }
                                />
                            </div>
                        )}
                    </>
                )}

                <Field>
                    <Label htmlFor="atn-respuesta">Respuesta</Label>
                    <textarea
                        id="atn-respuesta"
                        className="min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        placeholder="RESPUESTA"
                        value={form.data.respuesta}
                        onChange={(e) =>
                            form.setData('respuesta', e.target.value)
                        }
                    />
                </Field>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        <Send className="mr-2 size-4" /> Enviar
                    </Button>
                </div>
            </form>
        </CrudFormDialog>
    );
}
