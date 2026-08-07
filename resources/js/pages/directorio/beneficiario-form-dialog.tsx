import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { FotoField } from '@/components/foto-field';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { store } from '@/routes/directorio/beneficiarios';
import { Seccion } from './seccion';
import type { Opciones } from './types';
import { GENEROS, TIPOS_PERSONA } from './types';

type Form = Record<string, string | number | null>;

const CAMPOS_INICIALES: Form = {
    id: null,
    nombre: '',
    paterno: '',
    materno: '',
    alias: '',
    curp: '',
    genero: null,
    nacimiento: '',
    tipo: null,
    estado_civil_id: null,
    calle: '',
    num_ext: '',
    num_int: '',
    colonia: '',
    cp: '',
    estado_id: null,
    municipio_id: null,
    localidad_id: null,
    telefono: '',
    celular: '',
    celular2: '',
    correo: '',
    correo2: '',
    facebook: '',
    twitter: '',
    empresa: '',
    puesto: '',
    tel_empresa: '',
    ocupacion_id: null,
    profesion_id: null,
    sector_id: null,
    grupo: '',
    vinculo_municipal: '',
    vinculo_estatal: '',
    vinculo_federal: '',
    seccion: null,
    filiacion_id: null,
    antagonico_id: null,
    lider_id: null,
    influencia_id: null,
    asist_nombre: '',
    asist_movil: '',
    asist_correo: '',
    conyuge_nombre: '',
    conyuge_movil: '',
    conyuge_nacimiento: '',
    estatus: null,
    foto: '',
};

/** Alta y edición de individuos (legacy `directorio_agregar/editar.php`). */
export function BeneficiarioFormDialog({
    id,
    opciones,
    onClose,
}: {
    /** `null` para dar de alta. */
    id: number | null;
    opciones: Opciones;
    onClose: () => void;
}) {
    const form = useForm<Form>(CAMPOS_INICIALES);
    const [nombres, setNombres] = useState<{
        municipio: string | null;
        localidad: string | null;
    }>({ municipio: null, localidad: null });

    useEffect(() => {
        if (id === null) {
            return;
        }

        fetch(`/directorio/beneficiarios/${id}/registro`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((datos: Record<string, string | number | null> | null) => {
                if (!datos) {
                    return;
                }

                const valores: Form = { ...CAMPOS_INICIALES };

                for (const clave of Object.keys(CAMPOS_INICIALES)) {
                    valores[clave] = datos[clave] ?? CAMPOS_INICIALES[clave];
                }

                valores.id = datos.id;
                form.setData(valores);
                setNombres({
                    municipio: (datos.municipio_nombre as string) ?? null,
                    localidad: (datos.localidad_nombre as string) ?? null,
                });
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

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

    const texto = (clave: string, label: string, type = 'text') => (
        <FormInputField
            id={`bene-${clave}`}
            label={label}
            type={type}
            value={(form.data[clave] as string) ?? ''}
            error={form.errors[clave]}
            onChange={(e) => form.setData(clave, e.target.value)}
        />
    );

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="xl"
            title={id === null ? 'Nuevo individuo' : 'Editar individuo'}
            description="Captura los datos de la persona."
            submitLabel={id === null ? 'Guardar' : 'Guardar cambios'}
            processing={form.processing}
            onSubmit={enviar}
        >
            <FotoField
                value={(form.data.foto as string) || null}
                onChange={(v) => form.setData('foto', v ?? '')}
            />

            <Seccion titulo="Datos personales">
                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('nombre', 'Nombre(s)')}
                    {texto('paterno', 'Apellido paterno')}
                    {texto('materno', 'Apellido materno')}
                    {texto('alias', 'Alias')}
                    {texto('curp', 'CURP')}
                    {texto('nacimiento', 'Nacimiento', 'date')}
                    <SelectField
                        label="Género"
                        value={form.data.genero as number | null}
                        options={GENEROS}
                        error={form.errors.genero}
                        onChange={(v) => form.setData('genero', v)}
                    />
                    <SelectField
                        label="Tipo"
                        value={form.data.tipo as number | null}
                        options={TIPOS_PERSONA}
                        error={form.errors.tipo}
                        onChange={(v) => form.setData('tipo', v)}
                    />
                    <SelectField
                        label="Estado civil"
                        value={form.data.estado_civil_id as number | null}
                        options={opciones.estadosCiviles}
                        error={form.errors.estado_civil_id}
                        onChange={(v) => form.setData('estado_civil_id', v)}
                    />
                </div>
            </Seccion>

            <Seccion titulo="Domicilio">
                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('calle', 'Calle')}
                    {texto('num_ext', 'Núm. exterior')}
                    {texto('num_int', 'Núm. interior')}
                    {texto('colonia', 'Colonia')}
                    {texto('cp', 'C.P.')}
                </div>
                <UbicacionSelects
                    estados={opciones.estados}
                    value={{
                        estado_id: form.data.estado_id as number | null,
                        municipio_id: form.data.municipio_id as number | null,
                        localidad_id: form.data.localidad_id as number | null,
                    }}
                    municipioNombre={nombres.municipio}
                    localidadNombre={nombres.localidad}
                    errors={{
                        estado_id: form.errors.estado_id,
                        municipio_id: form.errors.municipio_id,
                        localidad_id: form.errors.localidad_id,
                    }}
                    onChange={(patch) =>
                        form.setData({ ...form.data, ...patch })
                    }
                    onSeccion={(seccion) => form.setData('seccion', seccion)}
                />
                {/* Igual que el legacy: la sección no se teclea, la pone la
                    localidad. Se muestra para poder confirmarla de un vistazo. */}
                <Field className="sm:max-w-56">
                    <Label htmlFor="seccion">Sección electoral</Label>
                    <Input
                        id="seccion"
                        readOnly
                        value={form.data.seccion ?? ''}
                        placeholder="La define la localidad"
                        className="tabular-nums"
                    />
                </Field>
            </Seccion>

            <Seccion titulo="Contacto">
                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('telefono', 'Teléfono')}
                    {texto('celular', 'Celular')}
                    {texto('celular2', 'Celular 2')}
                    {texto('correo', 'Correo')}
                    {texto('correo2', 'Correo 2')}
                    {texto('facebook', 'Facebook')}
                    {texto('twitter', 'Twitter/X')}
                </div>
            </Seccion>

            <Seccion titulo="Laboral y clasificación">
                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('empresa', 'Empresa')}
                    {texto('puesto', 'Puesto')}
                    {texto('tel_empresa', 'Tel. empresa')}
                    <SelectField
                        label="Ocupación"
                        value={form.data.ocupacion_id as number | null}
                        options={opciones.ocupaciones}
                        error={form.errors.ocupacion_id}
                        onChange={(v) => form.setData('ocupacion_id', v)}
                    />
                    <SelectField
                        label="Profesión"
                        value={form.data.profesion_id as number | null}
                        options={opciones.profesiones}
                        error={form.errors.profesion_id}
                        onChange={(v) => form.setData('profesion_id', v)}
                    />
                    <SelectField
                        label="Sector"
                        value={form.data.sector_id as number | null}
                        options={opciones.sectores}
                        error={form.errors.sector_id}
                        onChange={(v) => form.setData('sector_id', v)}
                    />
                    {texto('grupo', 'Grupo')}
                    {texto('vinculo_municipal', 'Vínculo municipal')}
                    {texto('vinculo_estatal', 'Vínculo estatal')}
                    {texto('vinculo_federal', 'Vínculo federal')}
                </div>
            </Seccion>

            <Seccion titulo="Perfil político">
                <div className="grid gap-4 sm:grid-cols-2">
                    <SelectField
                        label="Filiación"
                        value={form.data.filiacion_id as number | null}
                        options={opciones.filiaciones}
                        error={form.errors.filiacion_id}
                        onChange={(v) => form.setData('filiacion_id', v)}
                    />
                    <SelectField
                        label="Antagónico"
                        value={form.data.antagonico_id as number | null}
                        options={opciones.antagonicos}
                        error={form.errors.antagonico_id}
                        onChange={(v) => form.setData('antagonico_id', v)}
                    />
                    <SelectField
                        label="Líder"
                        value={form.data.lider_id as number | null}
                        options={opciones.lideres}
                        error={form.errors.lider_id}
                        onChange={(v) => form.setData('lider_id', v)}
                    />
                    <SelectField
                        label="Grado de influencia"
                        value={form.data.influencia_id as number | null}
                        options={opciones.influencias}
                        error={form.errors.influencia_id}
                        onChange={(v) => form.setData('influencia_id', v)}
                    />
                </div>
            </Seccion>

            <Seccion titulo="Asistente y cónyuge">
                <div className="grid gap-4 sm:grid-cols-3">
                    {texto('asist_nombre', 'Asistente')}
                    {texto('asist_movil', 'Móvil asistente')}
                    {texto('asist_correo', 'Correo asistente')}
                    {texto('conyuge_nombre', 'Cónyuge')}
                    {texto('conyuge_movil', 'Móvil cónyuge')}
                    {texto('conyuge_nacimiento', 'Nacimiento cónyuge', 'date')}
                </div>
            </Seccion>
        </CrudFormDialog>
    );
}
