import { router, useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { HistorialSeguimientos } from './historial-seguimientos';
import type { Opciones, Seguimiento, Solicitud } from './types';

/** Estatus del seguimiento, igual que el select del legacy. */
const ESTATUS_SEGUIMIENTO = [
    { id: 1, nombre: 'TURNAR' },
    { id: 2, nombre: 'NO APROBADA' },
    { id: 3, nombre: 'PARA RESOLVER' },
];

type Form = {
    estatus: number | null;
    dependencia_id: number | null;
    area_id: number | null;
    responsable: string;
    instruccion: string;
    comentario: string;
    fecha_comp: string;
    estado_resp_id: number | null;
    municipio_resp_id: number | null;
    localidad_resp_id: number | null;
};

/**
 * Pantalla "Seguimiento solicitud" del legacy (`seguimiento_add.php`): decide el
 * estatus de la solicitud y, si se turna, a qué dependencia/dirección va.
 */
export function SeguimientoDialog({
    solicitud,
    opciones,
    onClose,
}: {
    solicitud: Solicitud;
    opciones: Opciones;
    onClose: () => void;
}) {
    const [historial, setHistorial] = useState<Seguimiento[]>([]);
    const [notificado, setNotificado] = useState(solicitud.notificacion);

    const form = useForm<Form>({
        estatus: null,
        dependencia_id: null,
        area_id: null,
        responsable: '',
        instruccion: '',
        comentario: '',
        fecha_comp: solicitud.fecha_comp ?? '',
        estado_resp_id: solicitud.estado_resp_id,
        municipio_resp_id: solicitud.municipio_resp_id,
        localidad_resp_id: solicitud.localidad_resp_id,
    });

    useEffect(() => {
        fetch(`/gestion/solicitudes/${solicitud.id}/seguimientos`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : []))
            .then((data) => setHistorial(data as Seguimiento[]));
    }, [solicitud.id]);

    // "No aprobada" no se turna a nadie: se oculta dependencia/dirección/responsable.
    const turna = form.data.estatus !== 2;

    const areasFiltradas = opciones.areas.filter(
        (a) =>
            form.data.dependencia_id === null ||
            a.dependencia_id === form.data.dependencia_id,
    );

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/gestion/solicitudes/${solicitud.id}/seguimientos`, {
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

    const alternarNotificacion = (valor: boolean) => {
        setNotificado(valor);
        router.put(
            `/gestion/solicitudes/${solicitud.id}/notificar`,
            { notificacion: valor },
            {
                preserveScroll: true,
                onError: () => {
                    setNotificado(!valor);
                    toast.error('No se pudo actualizar la notificación.');
                },
            },
        );
    };

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="xl"
            showFooter={false}
            title={`Seguimiento — ${solicitud.folio_sistema ?? solicitud.folio ?? `#${solicitud.id}`}`}
            description={solicitud.solicitud ?? ''}
        >
            <form className="space-y-4" onSubmit={enviar}>
                <Field>
                    <Label>Ciudadano notificado</Label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={notificado}
                            disabled={solicitud.status !== 2}
                            onCheckedChange={(v) =>
                                alternarNotificacion(Boolean(v))
                            }
                        />
                        Se notificó al ciudadano
                    </label>
                    {solicitud.status !== 2 && (
                        <p className="text-xs text-muted-foreground">
                            Sólo aplica a solicitudes no aprobadas.
                        </p>
                    )}
                </Field>

                <div className="grid gap-4 sm:grid-cols-3">
                    <FormInputField
                        id="seg-fecha-comp"
                        label="Fecha compromiso de atención"
                        placeholder="AAAA-MM-DD"
                        value={form.data.fecha_comp}
                        error={form.errors.fecha_comp}
                        onChange={(e) =>
                            form.setData('fecha_comp', e.target.value)
                        }
                    />
                    <SelectField
                        label="Estatus"
                        value={form.data.estatus}
                        options={ESTATUS_SEGUIMIENTO}
                        error={form.errors.estatus}
                        onChange={(v) =>
                            form.setData({
                                ...form.data,
                                estatus: v,
                                ...(v === 2
                                    ? {
                                          dependencia_id: null,
                                          area_id: null,
                                          responsable: '',
                                          instruccion: '',
                                      }
                                    : {}),
                            })
                        }
                    />
                </div>

                <div className="space-y-2">
                    <p className="text-sm font-medium text-muted-foreground">
                        Localidad donde se va a entregar el apoyo
                    </p>
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
                                    ? {
                                          municipio_resp_id:
                                              patch.municipio_id ?? null,
                                      }
                                    : {}),
                                ...('localidad_id' in patch
                                    ? {
                                          localidad_resp_id:
                                              patch.localidad_id ?? null,
                                      }
                                    : {}),
                            })
                        }
                    />
                </div>

                {turna && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <SelectField
                            label="¿A quién se turna?"
                            value={form.data.dependencia_id}
                            options={opciones.dependencias}
                            error={form.errors.dependencia_id}
                            onChange={(v) =>
                                form.setData({
                                    ...form.data,
                                    dependencia_id: v,
                                    area_id: null,
                                })
                            }
                        />
                        <SelectField
                            label="Dirección"
                            value={form.data.area_id}
                            options={areasFiltradas}
                            error={form.errors.area_id}
                            disabled={form.data.dependencia_id === null}
                            onChange={(v) => form.setData('area_id', v)}
                        />
                        <FormInputField
                            id="seg-resp"
                            label="Nombre del responsable"
                            placeholder="NOMBRE DEL RESPONSABLE"
                            value={form.data.responsable}
                            error={form.errors.responsable}
                            onChange={(e) =>
                                form.setData('responsable', e.target.value)
                            }
                        />
                    </div>
                )}

                {turna && (
                    <Field>
                        <Label htmlFor="seg-instruccion">Instrucción</Label>
                        <textarea
                            id="seg-instruccion"
                            className="min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            placeholder="INSTRUCCIÓN"
                            value={form.data.instruccion}
                            onChange={(e) =>
                                form.setData('instruccion', e.target.value)
                            }
                        />
                    </Field>
                )}

                <Field>
                    <Label htmlFor="seg-comentario">Comentario</Label>
                    <textarea
                        id="seg-comentario"
                        className="min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                        placeholder="COMENTARIO"
                        value={form.data.comentario}
                        onChange={(e) =>
                            form.setData('comentario', e.target.value)
                        }
                    />
                </Field>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        <Send className="mr-2 size-4" /> Enviar
                    </Button>
                </div>
            </form>

            <div className="space-y-2 border-t pt-4">
                <p className="text-sm font-semibold">Detalles</p>
                <HistorialSeguimientos seguimientos={historial} />
            </div>
        </CrudFormDialog>
    );
}
