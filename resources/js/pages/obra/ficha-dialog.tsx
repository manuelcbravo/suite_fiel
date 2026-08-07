import { useForm } from '@inertiajs/react';
import { ChevronDown, MapPin } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AsyncSearchPicker } from '@/components/async-search-picker';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { MapaPunto } from '@/components/mapa-punto';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { store } from '@/routes/obra';
import type { Ficha, Opciones } from './types';
import { FICHA_VACIA } from './types';

/** Rótulo de sección dentro del formulario. */
function Seccion({ children }: { children: string }) {
    return (
        <h3 className="flex items-center gap-3 pt-2 text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
            <span className="whitespace-nowrap">{children}</span>
            <span className="h-px flex-1 bg-border" />
        </h3>
    );
}

function Texto({
    label,
    value,
    onChange,
    filas = 3,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    filas?: number;
}) {
    return (
        <Field>
            <Label>{label}</Label>
            <textarea
                rows={filas}
                className="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
        </Field>
    );
}

export function FichaDialog({
    obraId,
    opciones,
    onClose,
}: {
    obraId: number | 'nueva' | null;
    opciones: Opciones;
    onClose: () => void;
}) {
    const form = useForm<Ficha>(FICHA_VACIA);
    const [cargando, setCargando] = useState(typeof obraId === 'number');
    const [ubic, setUbic] = useState({
        estado_id: null as number | null,
        municipio_id: null as number | null,
        localidad_id: null as number | null,
    });
    const [nombres, setNombres] = useState<{
        municipio: string | null;
        localidad: string | null;
    }>({ municipio: null, localidad: null });
    const [mapaAbierto, setMapaAbierto] = useState(false);

    // Sólo al editar hay algo que traer: el listado no carga la ficha completa
    // por peso. El alta arranca con el formulario vacío y el diálogo se vuelve
    // a montar en cada apertura (`key` en el padre), así que no hay que
    // limpiarlo a mano.
    useEffect(() => {
        if (typeof obraId !== 'number') {
            return;
        }

        let vigente = true;

        fetch(`/obra/${obraId}/ficha`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : Promise.reject(r)))
            .then((datos) => {
                if (!vigente) {
                    return;
                }

                const limpio = Object.fromEntries(
                    Object.entries(FICHA_VACIA).map(([k, vacio]) => [
                        k,
                        datos[k] ?? vacio,
                    ]),
                ) as Ficha;

                form.setData(limpio);
                setUbic({
                    estado_id: datos.estado_id ?? null,
                    municipio_id: datos.municipio_id ?? null,
                    localidad_id: datos.localidad_id ?? null,
                });
                setNombres({
                    municipio: datos.municipio_nombre ?? null,
                    localidad: datos.localidad_nombre ?? null,
                });
                setMapaAbierto(limpio.latitud !== '' && limpio.longitud !== '');
            })
            .catch(() => toast.error('No se pudo cargar la ficha técnica.'))
            .finally(() => {
                if (vigente) {
                    setCargando(false);
                }
            });

        return () => {
            vigente = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [obraId]);

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((datos) => ({
            ...datos,
            localidad_id: ubic.localidad_id,
        }));
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errores) =>
                toast.error(
                    resolveFormErrorMessage(errores, 'Revisa los campos.'),
                ),
        });
    };

    const nueva = obraId === 'nueva';
    const subejesPmd = opciones.pmdSubejes.filter(
        (s) => s.eje_id === form.data.pmd_eje_id,
    );
    const subejesPed = opciones.pedSubejes.filter(
        (s) => s.eje_id === form.data.ped_eje_id,
    );

    return (
        <CrudFormDialog
            open={obraId !== null}
            onOpenChange={(o) => !o && onClose()}
            size="xl"
            title={nueva ? 'Importar solicitud' : 'Ficha técnica'}
            description={
                nueva
                    ? 'Toda obra nace de una solicitud: elige de cuál.'
                    : 'Los datos con que se justifica y dimensiona la obra.'
            }
            submitLabel={nueva ? 'Crear obra' : 'Guardar cambios'}
            processing={form.processing || cargando}
            onSubmit={enviar}
        >
            {/* El formulario no se monta hasta tener el dato: si un select
                nace sin su opción, descarta el valor y la ficha se guardaría
                a medias. */}
            {cargando ? (
                <p className="py-12 text-center text-sm text-muted-foreground">
                    Cargando la ficha técnica…
                </p>
            ) : (
                <>
                    {nueva && (
                        <AsyncSearchPicker
                            label="Solicitud de origen"
                            searchUrl="/obra/solicitudes"
                            value={form.data.solicitud_id}
                            error={form.errors.solicitud_id}
                            onChange={(id) => form.setData('solicitud_id', id)}
                            placeholder="Buscar por folio o texto de la solicitud..."
                        />
                    )}

                    <Seccion>Identificación</Seccion>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormInputField
                            id="obra-num"
                            label="Número de obra"
                            value={form.data.num_obra}
                            error={form.errors.num_obra}
                            onChange={(e) =>
                                form.setData('num_obra', e.target.value)
                            }
                        />
                        <FormInputField
                            id="obra-dependencia"
                            label="Dependencia responsable"
                            value={form.data.dependencia}
                            error={form.errors.dependencia}
                            onChange={(e) =>
                                form.setData('dependencia', e.target.value)
                            }
                        />
                        <FormInputField
                            id="obra-financ"
                            label="Propuesta de financiamiento"
                            value={form.data.prop_financ}
                            error={form.errors.prop_financ}
                            onChange={(e) =>
                                form.setData('prop_financ', e.target.value)
                            }
                        />
                    </div>

                    <Texto
                        label="Nombre de la obra"
                        value={form.data.nombre}
                        onChange={(v) => form.setData('nombre', v)}
                        filas={2}
                    />

                    <div className="grid gap-4 sm:grid-cols-3">
                        <SelectField
                            label="Concepto"
                            value={form.data.concepto_id}
                            options={opciones.conceptos}
                            onChange={(v) => form.setData('concepto_id', v)}
                        />
                        <SelectField
                            label="Segmento poblacional"
                            value={form.data.sector_id}
                            options={opciones.sectores}
                            onChange={(v) => form.setData('sector_id', v)}
                        />
                        <SelectField
                            label="Unidad de medida de la meta"
                            value={form.data.unidad_medida_id}
                            options={opciones.unidades}
                            onChange={(v) =>
                                form.setData('unidad_medida_id', v)
                            }
                        />
                        <FormInputField
                            id="obra-monto"
                            label="Monto de inversión"
                            value={form.data.monto_inv}
                            error={form.errors.monto_inv}
                            onChange={(e) =>
                                form.setData('monto_inv', e.target.value)
                            }
                        />
                        <FormInputField
                            id="obra-benef"
                            label="Número de beneficiarios"
                            value={form.data.num_benef}
                            error={form.errors.num_benef}
                            onChange={(e) =>
                                form.setData('num_benef', e.target.value)
                            }
                        />
                    </div>

                    <Texto
                        label="Descripción de la obra"
                        value={form.data.descripcion}
                        onChange={(v) => form.setData('descripcion', v)}
                    />
                    <Texto
                        label="Metas"
                        value={form.data.metas}
                        onChange={(v) => form.setData('metas', v)}
                        filas={2}
                    />

                    <Seccion>Ubicación</Seccion>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormInputField
                            id="obra-calle"
                            label="Calle y número"
                            value={form.data.calle_num}
                            error={form.errors.calle_num}
                            onChange={(e) =>
                                form.setData('calle_num', e.target.value)
                            }
                        />
                        <FormInputField
                            id="obra-colonias"
                            label="Colonias beneficiadas"
                            value={form.data.col_beneficiados}
                            onChange={(e) =>
                                form.setData('col_beneficiados', e.target.value)
                            }
                        />
                    </div>
                    <UbicacionSelects
                        estados={opciones.estados}
                        value={ubic}
                        municipioNombre={nombres.municipio}
                        localidadNombre={nombres.localidad}
                        onChange={(patch) => setUbic({ ...ubic, ...patch })}
                    />
                    <Texto
                        label="Referencia de ubicación"
                        value={form.data.referencia_mapa}
                        onChange={(v) => form.setData('referencia_mapa', v)}
                        filas={2}
                    />

                    {/* La coordenada se marca en el mapa; es opcional. */}
                    <Collapsible
                        open={mapaAbierto}
                        onOpenChange={setMapaAbierto}
                    >
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <CollapsibleTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="group"
                                >
                                    <MapPin className="mr-2 size-4" />
                                    {form.data.latitud
                                        ? 'Ubicación de la obra'
                                        : 'Marcar ubicación en el mapa'}
                                    <ChevronDown className="ml-2 size-4 transition-transform group-data-[state=open]:rotate-180" />
                                </Button>
                            </CollapsibleTrigger>
                            {form.data.latitud ? (
                                <button
                                    type="button"
                                    className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                    onClick={() => {
                                        form.setData('latitud', '');
                                        form.setData('longitud', '');
                                    }}
                                >
                                    Quitar punto
                                </button>
                            ) : (
                                <span className="text-xs text-muted-foreground">
                                    Opcional.
                                </span>
                            )}
                        </div>
                        <CollapsibleContent className="pt-3">
                            <MapaPunto
                                latitud={form.data.latitud}
                                longitud={form.data.longitud}
                                onChange={(lat, lon) => {
                                    form.setData('latitud', lat);
                                    form.setData('longitud', lon);
                                }}
                                alto="h-56"
                                leyenda="dónde va la obra"
                            />
                        </CollapsibleContent>
                    </Collapsible>

                    <Seccion>Alineación a los planes de desarrollo</Seccion>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            label="Eje del Plan Municipal"
                            value={form.data.pmd_eje_id}
                            options={opciones.pmdEjes}
                            onChange={(v) => {
                                // Sólo se limpia el subeje si el eje de verdad cambió:
                                // el select puede re-emitir su propio valor y eso no
                                // debe borrar lo capturado.
                                if (v === form.data.pmd_eje_id) {
                                    return;
                                }

                                form.setData('pmd_eje_id', v);
                                form.setData('pmd_subeje_id', null);
                            }}
                        />
                        <SelectField
                            label="Subeje del Plan Municipal"
                            value={form.data.pmd_subeje_id}
                            options={subejesPmd}
                            onChange={(v) => form.setData('pmd_subeje_id', v)}
                        />
                        <SelectField
                            label="Eje del Plan Estatal"
                            value={form.data.ped_eje_id}
                            options={opciones.pedEjes}
                            onChange={(v) => {
                                // Sólo se limpia el subeje si el eje de verdad cambió:
                                // el select puede re-emitir su propio valor y eso no
                                // debe borrar lo capturado.
                                if (v === form.data.ped_eje_id) {
                                    return;
                                }

                                form.setData('ped_eje_id', v);
                                form.setData('ped_subeje_id', null);
                            }}
                        />
                        <SelectField
                            label="Subeje del Plan Estatal"
                            value={form.data.ped_subeje_id}
                            options={subejesPed}
                            onChange={(v) => form.setData('ped_subeje_id', v)}
                        />
                    </div>

                    <Seccion>Análisis y justificación</Seccion>
                    <Texto
                        label="Análisis de la problemática"
                        value={form.data.analisis_problematica}
                        onChange={(v) =>
                            form.setData('analisis_problematica', v)
                        }
                    />
                    <Texto
                        label="Qué pasa si no se hace la obra"
                        value={form.data.analisis_sinobra}
                        onChange={(v) => form.setData('analisis_sinobra', v)}
                    />
                    <Texto
                        label="Alternativas de solución"
                        value={form.data.alternativa_solucion}
                        onChange={(v) =>
                            form.setData('alternativa_solucion', v)
                        }
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Texto
                            label="Aspectos técnicos relevantes"
                            value={form.data.asp_tecnicos}
                            onChange={(v) => form.setData('asp_tecnicos', v)}
                        />
                        <Texto
                            label="Aspectos ambientales relevantes"
                            value={form.data.asp_ambientales}
                            onChange={(v) => form.setData('asp_ambientales', v)}
                        />
                        <Texto
                            label="Aspectos legales relevantes"
                            value={form.data.asp_legales}
                            onChange={(v) => form.setData('asp_legales', v)}
                        />
                        <Texto
                            label="Aspectos sociales relevantes"
                            value={form.data.asp_sociales}
                            onChange={(v) => form.setData('asp_sociales', v)}
                        />
                        <Texto
                            label="Rentabilidad social"
                            value={form.data.rentab_social}
                            onChange={(v) => form.setData('rentab_social', v)}
                        />
                        <Texto
                            label="Impacto social"
                            value={form.data.impacto_social}
                            onChange={(v) => form.setData('impacto_social', v)}
                        />
                    </div>
                    <Texto
                        label="Descripción de rentabilidad e impacto"
                        value={form.data.descrip_renta_impac}
                        onChange={(v) => form.setData('descrip_renta_impac', v)}
                    />
                    <Texto
                        label="Factibilidad"
                        value={form.data.factibilidad}
                        onChange={(v) => form.setData('factibilidad', v)}
                    />
                    <Texto
                        label="Conclusión"
                        value={form.data.conclusion}
                        onChange={(v) => form.setData('conclusion', v)}
                    />

                    <label className="flex items-center gap-2 pt-2 text-sm">
                        <Checkbox
                            checked={form.data.ficha_completo}
                            onCheckedChange={(v) =>
                                form.setData('ficha_completo', Boolean(v))
                            }
                        />
                        Dar por cerrada la ficha técnica
                    </label>
                </>
            )}
        </CrudFormDialog>
    );
}
