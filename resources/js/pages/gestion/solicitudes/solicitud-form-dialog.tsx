import { useForm } from '@inertiajs/react';
import { ChevronDown, MapPin } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AsyncSearchPicker } from '@/components/async-search-picker';
import { BeneficiarioPicker } from '@/components/beneficiario-picker';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { FotoField } from '@/components/foto-field';
import { MapaPunto } from '@/components/mapa-punto';
import { MultiCheckField } from '@/components/multi-check-field';
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
import { store } from '@/routes/gestion/solicitudes';
import type { Grupo, Opciones, Solicitud } from './types';

/**
 * Dónde ocurre la solicitud, marcado en el mapa. Va plegado: es opcional y la
 * mayoría de las solicitudes no lleva coordenada, así que no debe robarle
 * espacio al resto del formulario.
 */
function UbicacionEnMapa({
    latitud,
    longitud,
    onChange,
}: {
    latitud: string;
    longitud: string;
    onChange: (latitud: string, longitud: string) => void;
}) {
    const marcado = latitud !== '' && longitud !== '';
    const [abierto, setAbierto] = useState(marcado);

    return (
        <Collapsible open={abierto} onOpenChange={setAbierto}>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <CollapsibleTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="group"
                    >
                        <MapPin className="mr-2 size-4" />
                        {marcado
                            ? 'Ubicación de la solicitud'
                            : 'Marcar ubicación en el mapa'}
                        <ChevronDown className="ml-2 size-4 transition-transform group-data-[state=open]:rotate-180" />
                    </Button>
                </CollapsibleTrigger>

                {marcado ? (
                    <>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {Number(latitud).toFixed(5)},{' '}
                            {Number(longitud).toFixed(5)}
                        </span>
                        <button
                            type="button"
                            className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                            onClick={() => onChange('', '')}
                        >
                            Quitar punto
                        </button>
                    </>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Opcional.
                    </span>
                )}
            </div>

            <CollapsibleContent className="pt-3">
                <MapaPunto
                    latitud={latitud}
                    longitud={longitud}
                    onChange={onChange}
                    alto="h-56"
                    leyenda="a qué punto se refiere la solicitud"
                />
            </CollapsibleContent>
        </Collapsible>
    );
}

/** `bene_final` del legacy: quién recibe realmente el apoyo. */
const BENEFICIARIOS = [
    { id: 1, nombre: 'SOLICITANTE' },
    { id: 2, nombre: 'OTRA PERSONA' },
    { id: 3, nombre: 'UN GRUPO DE PERSONAS / COMUNIDAD' },
];

/** `prioridad` del legacy. */
const CLASIFICACIONES = [
    { id: 1, nombre: 'URGENTE' },
    { id: 2, nombre: 'PRIORITARIA' },
    { id: 3, nombre: 'NORMAL' },
];

type Form = {
    id: number | null;
    solicitante_tipo: 'beneficiario' | 'organizacion';
    beneficiario_id: number | null;
    organizacion_id: number | null;
    folio: string;
    fecha_recepcion: string;
    procedencia_id: number | null;
    control_administrativo: boolean;
    fecha_comp: string;
    estado_resp_id: number | null;
    municipio_resp_id: number | null;
    localidad_resp_id: number | null;
    latitud: string;
    longitud: string;
    bene_final: number | null;
    desc_bene: string;
    prioridad: number | null;
    imagen: string | null;
    solicitud: string;
    rubros: number[];
    sectores: number[];
};

const vacio = (grupo: Grupo | null): Form => ({
    id: null,
    solicitante_tipo: grupo?.tipo ?? 'beneficiario',
    beneficiario_id: grupo?.tipo === 'beneficiario' ? grupo.id : null,
    organizacion_id: grupo?.tipo === 'organizacion' ? grupo.id : null,
    folio: '',
    fecha_recepcion: '',
    procedencia_id: null,
    control_administrativo: false,
    fecha_comp: '',
    estado_resp_id: null,
    municipio_resp_id: null,
    localidad_resp_id: null,
    latitud: '',
    longitud: '',
    bene_final: null,
    desc_bene: '',
    prioridad: null,
    imagen: null,
    solicitud: '',
    rubros: [],
    sectores: [],
});

const desde = (s: Solicitud): Form => ({
    id: s.id,
    solicitante_tipo: s.solicitante_tipo,
    beneficiario_id: s.solicitante_tipo === 'beneficiario' ? s.solicitante_id : null,
    organizacion_id: s.solicitante_tipo === 'organizacion' ? s.solicitante_id : null,
    folio: s.folio ?? '',
    fecha_recepcion: s.fecha_recepcion ?? '',
    procedencia_id: s.procedencia_id,
    control_administrativo: s.control_administrativo,
    fecha_comp: s.fecha_comp ?? '',
    estado_resp_id: s.estado_resp_id,
    municipio_resp_id: s.municipio_resp_id,
    localidad_resp_id: s.localidad_resp_id,
    latitud: s.latitud ?? '',
    longitud: s.longitud ?? '',
    bene_final: s.bene_final,
    desc_bene: s.desc_bene ?? '',
    prioridad: s.prioridad,
    imagen: s.imagen,
    solicitud: s.solicitud ?? '',
    rubros: s.rubros ?? [],
    sectores: s.sectores ?? [],
});

/**
 * Alta y edición de la solicitud (legacy `compromiso_add.php` /
 * `compromiso_edit.php`). El estatus no se captura aquí: lo mueve el flujo de
 * seguimiento y de atención.
 */
export function SolicitudFormDialog({
    solicitud,
    grupo,
    opciones,
    onClose,
}: {
    /** Solicitud a editar; `null` para dar de alta. */
    solicitud: Solicitud | null;
    /** Solicitante al que se le da de alta la solicitud. */
    grupo: Grupo | null;
    opciones: Opciones;
    onClose: () => void;
}) {
    const form = useForm<Form>(solicitud ? desde(solicitud) : vacio(grupo));
    const editando = solicitud !== null;

    // El legacy oculta el nombre del beneficiario cuando es el propio solicitante.
    const pideDescripcion =
        form.data.bene_final === 2 || form.data.bene_final === 3;

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
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

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="xl"
            title={editando ? 'Editar solicitud' : 'Agregar solicitud'}
            description={
                grupo?.nombre ??
                solicitud?.solicitante_nombre ??
                'Captura los datos de la solicitud.'
            }
            submitLabel={editando ? 'Guardar cambios' : 'Guardar'}
            processing={form.processing}
            onSubmit={enviar}
        >
            {/* Al dar de alta desde la fila del solicitante, éste ya viene fijo. */}
            {!grupo && (
                <>
                    <Field>
                        <Label>Tipo de solicitante</Label>
                        <div className="flex gap-2">
                            {(
                                [
                                    ['beneficiario', 'Beneficiario'],
                                    ['organizacion', 'Organización'],
                                ] as const
                            ).map(([valor, etiqueta]) => (
                                <Button
                                    key={valor}
                                    type="button"
                                    variant={
                                        form.data.solicitante_tipo === valor
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="sm"
                                    onClick={() =>
                                        form.setData({
                                            ...form.data,
                                            solicitante_tipo: valor,
                                            beneficiario_id: null,
                                            organizacion_id: null,
                                        })
                                    }
                                >
                                    {etiqueta}
                                </Button>
                            ))}
                        </div>
                    </Field>

                    {form.data.solicitante_tipo === 'organizacion' ? (
                        <AsyncSearchPicker
                            label="Solicitante (organización)"
                            searchUrl="/directorio/organizaciones/buscar"
                            placeholder="Buscar organización (mín. 2 letras)..."
                            value={form.data.organizacion_id}
                            valueLabel={
                                solicitud?.solicitante_tipo === 'organizacion'
                                    ? solicitud.solicitante_nombre
                                    : null
                            }
                            error={form.errors.organizacion_id}
                            onChange={(id) =>
                                form.setData('organizacion_id', id)
                            }
                        />
                    ) : (
                        <BeneficiarioPicker
                            label="Solicitante (beneficiario)"
                            value={form.data.beneficiario_id}
                            valueLabel={
                                solicitud?.solicitante_tipo === 'beneficiario'
                                    ? solicitud.solicitante_nombre
                                    : null
                            }
                            error={form.errors.beneficiario_id}
                            onChange={(id) =>
                                form.setData('beneficiario_id', id)
                            }
                        />
                    )}
                </>
            )}

            <div className="grid gap-4 sm:grid-cols-3">
                <FormInputField
                    id="sol-folio"
                    label="Clave o Folio de recepción"
                    placeholder="CLAVE O FOLIO DE RECEPCIÓN"
                    value={form.data.folio}
                    error={form.errors.folio}
                    onChange={(e) => form.setData('folio', e.target.value)}
                />
                <FormInputField
                    id="sol-fecha"
                    type="date"
                    label="Fecha de recepción"
                    value={form.data.fecha_recepcion}
                    error={form.errors.fecha_recepcion}
                    onChange={(e) =>
                        form.setData('fecha_recepcion', e.target.value)
                    }
                />
                <SelectField
                    label="Origen de la solicitud"
                    value={form.data.procedencia_id}
                    options={opciones.procedencias}
                    error={form.errors.procedencia_id}
                    onChange={(v) => form.setData('procedencia_id', v)}
                />
            </div>

            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={form.data.control_administrativo}
                    onCheckedChange={(v) =>
                        form.setData('control_administrativo', Boolean(v))
                    }
                />
                Solicitud de control administrativo
            </label>

            <div className="grid gap-4 sm:grid-cols-3">
                <FormInputField
                    id="sol-fecha-comp"
                    label="Fecha compromiso de atención"
                    placeholder="AAAA-MM-DD"
                    value={form.data.fecha_comp}
                    error={form.errors.fecha_comp}
                    onChange={(e) => form.setData('fecha_comp', e.target.value)}
                />
            </div>

            {/* La coordenada se marca en el mapa, no se teclea. Va plegado
                porque es opcional y la mayoría de las solicitudes no la usa. */}
            <UbicacionEnMapa
                latitud={form.data.latitud}
                longitud={form.data.longitud}
                onChange={(latitud, longitud) => {
                    form.setData('latitud', latitud);
                    form.setData('longitud', longitud);
                }}
            />

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
                    municipioNombre={solicitud?.municipio_resp_nombre}
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

            <div className="grid gap-4 sm:grid-cols-3">
                <SelectField
                    label="¿Quién es el beneficiario?"
                    value={form.data.bene_final}
                    options={BENEFICIARIOS}
                    error={form.errors.bene_final}
                    onChange={(v) => form.setData('bene_final', v)}
                />
                {pideDescripcion ? (
                    <FormInputField
                        id="sol-desc-bene"
                        label="Información del beneficiario"
                        placeholder="INFORMACIÓN DEL BENEFICIARIO"
                        value={form.data.desc_bene}
                        error={form.errors.desc_bene}
                        onChange={(e) =>
                            form.setData('desc_bene', e.target.value)
                        }
                    />
                ) : (
                    <div />
                )}
                <SelectField
                    label="Clasificación"
                    value={form.data.prioridad}
                    options={CLASIFICACIONES}
                    error={form.errors.prioridad}
                    onChange={(v) => form.setData('prioridad', v)}
                />
            </div>

            <FotoField
                label="Imagen"
                value={form.data.imagen}
                onChange={(dataUri) => form.setData('imagen', dataUri)}
            />

            <Field>
                <Label htmlFor="sol-solicitud">Solicitud</Label>
                <textarea
                    id="sol-solicitud"
                    className="min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                    placeholder="DESCRIPCIÓN DE LA SOLICITUD"
                    value={form.data.solicitud}
                    onChange={(e) => form.setData('solicitud', e.target.value)}
                />
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                <MultiCheckField
                    label="Rubros"
                    options={opciones.rubros}
                    value={form.data.rubros}
                    error={form.errors.rubros}
                    onChange={(v) => form.setData('rubros', v)}
                />
                <MultiCheckField
                    label="Segmento poblacional"
                    options={opciones.sectores}
                    value={form.data.sectores}
                    error={form.errors.sectores}
                    onChange={(v) => form.setData('sectores', v)}
                />
            </div>
        </CrudFormDialog>
    );
}
