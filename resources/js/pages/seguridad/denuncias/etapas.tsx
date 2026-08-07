import { useForm } from '@inertiajs/react';
import { ClipboardCheck, FolderCheck } from 'lucide-react';
import { toast } from 'sonner';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { MapaPunto } from '@/components/mapa-punto';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';

type Opcion = { id: number; nombre: string };

export type DenunciaEtapa = {
    id: number;
    denuncia: string | null;
    atendido_por: string | null;
    fecha_atencion: string | null;
    hora_atencion: string | null;
    descripcion_situacion: string | null;
    tipo_incidencia_id: number | null;
    nivel_violencia_id: number | null;
    seg_sector_id: number | null;
    estado_id: number | null;
    municipio_id: number | null;
    localidad_id: number | null;
    localidad_nombre: string | null;
    municipio_nombre: string | null;
    latitud: string | null;
    longitud: string | null;
    acciones: string | null;
    acuerdos_convenios: string | null;
    conclusion: string | null;
    con_atencion: boolean;
    con_termino: boolean;
};

export type OpcionesEtapa = {
    tiposIncidencia: Opcion[];
    nivelesViolencia: Opcion[];
    sectores: Opcion[];
    estados: Opcion[];
};

/** Área de texto con etiqueta, para las descripciones largas de las etapas. */
function AreaTexto({
    id,
    label,
    placeholder,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    placeholder?: string;
    value: string;
    error?: string;
    onChange: (valor: string) => void;
}) {
    return (
        <Field>
            <Label htmlFor={id}>{label}</Label>
            <textarea
                id={id}
                className="min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                placeholder={placeholder}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            {error && <p className="text-sm text-destructive">{error}</p>}
        </Field>
    );
}

/**
 * Toma de conocimiento (legacy `dnncs_tncn.php`): quién atiende, cuándo, qué
 * pasó y cómo se clasifica. Al guardar, la denuncia pasa a "En atención".
 */
export function TomaConocimientoDialog({
    denuncia,
    opciones,
    onClose,
}: {
    denuncia: DenunciaEtapa;
    opciones: OpcionesEtapa;
    onClose: () => void;
}) {
    const hoy = new Date().toISOString().slice(0, 10);
    const form = useForm({
        atendido_por: denuncia.atendido_por ?? '',
        fecha_atencion: denuncia.fecha_atencion ?? hoy,
        hora_atencion:
            denuncia.hora_atencion ?? new Date().toTimeString().slice(0, 5),
        descripcion_situacion: denuncia.descripcion_situacion ?? '',
        tipo_incidencia_id: denuncia.tipo_incidencia_id,
        nivel_violencia_id: denuncia.nivel_violencia_id,
        seg_sector_id: denuncia.seg_sector_id,
        estado_id: denuncia.estado_id,
        municipio_id: denuncia.municipio_id,
        localidad_id: denuncia.localidad_id,
        latitud: denuncia.latitud ?? '',
        longitud: denuncia.longitud ?? '',
        acciones: denuncia.acciones ?? '',
    });

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/seguridad/denuncias/${denuncia.id}/atender`, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errores) =>
                toast.error(
                    resolveFormErrorMessage(errores, 'Verifica los campos marcados.'),
                ),
        });
    };

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="xl"
            title="Toma de conocimiento"
            description={denuncia.denuncia ?? ''}
            submitLabel="Guardar toma de conocimiento"
            processing={form.processing}
            onSubmit={enviar}
        >
            <div className="grid gap-4 sm:grid-cols-3">
                <FormInputField
                    id="tc-responsable"
                    label="Quién atiende"
                    placeholder="Nombre del oficial responsable"
                    value={form.data.atendido_por}
                    error={form.errors.atendido_por}
                    onChange={(e) => form.setData('atendido_por', e.target.value)}
                />
                <FormInputField
                    id="tc-fecha"
                    type="date"
                    label="Fecha"
                    value={form.data.fecha_atencion}
                    error={form.errors.fecha_atencion}
                    onChange={(e) => form.setData('fecha_atencion', e.target.value)}
                />
                <FormInputField
                    id="tc-hora"
                    type="time"
                    label="Hora"
                    value={form.data.hora_atencion}
                    error={form.errors.hora_atencion}
                    onChange={(e) => form.setData('hora_atencion', e.target.value)}
                />
            </div>

            <AreaTexto
                id="tc-situacion"
                label="Descripción de la situación"
                placeholder="Qué se encontró al llegar"
                value={form.data.descripcion_situacion}
                error={form.errors.descripcion_situacion}
                onChange={(v) => form.setData('descripcion_situacion', v)}
            />

            <div className="grid gap-4 sm:grid-cols-3">
                <SelectField
                    label="Presunto delito"
                    value={form.data.tipo_incidencia_id}
                    options={opciones.tiposIncidencia}
                    error={form.errors.tipo_incidencia_id}
                    onChange={(v) => form.setData('tipo_incidencia_id', v)}
                />
                <SelectField
                    label="Nivel de violencia"
                    value={form.data.nivel_violencia_id}
                    options={opciones.nivelesViolencia}
                    error={form.errors.nivel_violencia_id}
                    onChange={(v) => form.setData('nivel_violencia_id', v)}
                />
                <SelectField
                    label="Sector"
                    value={form.data.seg_sector_id}
                    options={opciones.sectores}
                    error={form.errors.seg_sector_id}
                    onChange={(v) => form.setData('seg_sector_id', v)}
                />
            </div>

            <div className="space-y-2">
                <p className="text-sm font-medium text-muted-foreground">
                    Dónde ocurrió
                </p>
                <UbicacionSelects
                    estados={opciones.estados}
                    value={{
                        estado_id: form.data.estado_id,
                        municipio_id: form.data.municipio_id,
                        localidad_id: form.data.localidad_id,
                    }}
                    municipioNombre={denuncia.municipio_nombre}
                    localidadNombre={denuncia.localidad_nombre}
                    errors={{ localidad_id: form.errors.localidad_id }}
                    onChange={(patch) => form.setData({ ...form.data, ...patch })}
                />
                <MapaPunto
                    latitud={form.data.latitud}
                    longitud={form.data.longitud}
                    onChange={(latitud, longitud) =>
                        form.setData({ ...form.data, latitud, longitud })
                    }
                />
            </div>

            <AreaTexto
                id="tc-acciones"
                label="Acciones realizadas"
                value={form.data.acciones}
                error={form.errors.acciones}
                onChange={(v) => form.setData('acciones', v)}
            />
        </CrudFormDialog>
    );
}

/**
 * Término (legacy `dnncs_trmn.php`): acuerdos y conclusión. La casilla cierra
 * la denuncia; el backend no deja cerrarla sin toma de conocimiento.
 */
export function TerminoDialog({
    denuncia,
    onClose,
}: {
    denuncia: DenunciaEtapa;
    onClose: () => void;
}) {
    const form = useForm({
        acuerdos_convenios: denuncia.acuerdos_convenios ?? '',
        conclusion: denuncia.conclusion ?? '',
        con_termino: denuncia.con_termino,
    });

    const enviar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/seguridad/denuncias/${denuncia.id}/concluir`, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errores) =>
                resolveFormErrorMessage(errores, 'No se pudo guardar el término.'),
        });
    };

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="lg"
            title="Término"
            description={denuncia.denuncia ?? ''}
            submitLabel="Guardar término"
            processing={form.processing}
            onSubmit={enviar}
        >
            <AreaTexto
                id="tr-acuerdos"
                label="Acuerdos y convenios"
                value={form.data.acuerdos_convenios}
                error={form.errors.acuerdos_convenios}
                onChange={(v) => form.setData('acuerdos_convenios', v)}
            />

            <AreaTexto
                id="tr-conclusion"
                label="Conclusión"
                value={form.data.conclusion}
                error={form.errors.conclusion}
                onChange={(v) => form.setData('conclusion', v)}
            />

            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    checked={form.data.con_termino}
                    disabled={!denuncia.con_atencion}
                    onCheckedChange={(v) => form.setData('con_termino', Boolean(v))}
                />
                Dar por concluida la denuncia
            </label>
            {!denuncia.con_atencion && (
                <p className="text-xs text-muted-foreground">
                    Primero registra la toma de conocimiento.
                </p>
            )}
            {form.errors.con_termino && (
                <p className="text-sm text-destructive">{form.errors.con_termino}</p>
            )}
        </CrudFormDialog>
    );
}

export const ICONO_ATENCION = ClipboardCheck;
export const ICONO_TERMINO = FolderCheck;
