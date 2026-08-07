import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { BeneficiarioPicker } from '@/components/beneficiario-picker';
import { CrudFormDialog } from '@/components/crud-form-dialog';
import { FormInputField } from '@/components/form-input-field';
import { FotoField } from '@/components/foto-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';
import { resolveFormErrorMessage } from '@/lib/form-error-message';
import { store } from '@/routes/directorio/proveedores';
import type { Opciones } from './types';

type Form = Record<string, string | number | null>;

const CAMPOS_INICIALES: Form = {
    id: null,
    nombre: '',
    rfc: '',
    representante_id: null,
    especialidad: '',
    calificacion: '',
    num_prov_gob: '',
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
    correo: '',
    foto: '',
};

/** Alta y edición de proveedores / contratistas. */
export function ProveedorFormDialog({
    id,
    opciones,
    onClose,
}: {
    id: number | null;
    opciones: Opciones;
    onClose: () => void;
}) {
    const form = useForm<Form>(CAMPOS_INICIALES);
    const [nombres, setNombres] = useState<{
        municipio: string | null;
        localidad: string | null;
        representante: string | null;
    }>({ municipio: null, localidad: null, representante: null });

    useEffect(() => {
        if (id === null) {
            return;
        }

        fetch(`/directorio/proveedores/${id}/registro`, {
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
                    representante: (datos.representante_nombre as string) ?? null,
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
                    resolveFormErrorMessage(errores, 'Verifica los campos marcados.'),
                ),
        });
    };

    const texto = (clave: string, label: string) => (
        <FormInputField
            id={`prov-${clave}`}
            label={label}
            value={(form.data[clave] as string) ?? ''}
            error={form.errors[clave]}
            onChange={(e) => form.setData(clave, e.target.value)}
        />
    );

    return (
        <CrudFormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            size="lg"
            title={id === null ? 'Nuevo proveedor' : 'Editar proveedor'}
            description="Captura los datos del proveedor."
            submitLabel={id === null ? 'Guardar' : 'Guardar cambios'}
            processing={form.processing}
            onSubmit={enviar}
        >
            <FotoField
                value={(form.data.foto as string) || null}
                onChange={(v) => form.setData('foto', v ?? '')}
            />

            <div className="grid gap-4 sm:grid-cols-2">
                {texto('nombre', 'Nombre / Razón social')}
                {texto('rfc', 'RFC')}
                {texto('especialidad', 'Especialidad')}
                {texto('calificacion', 'Calificación (0-10)')}
                {texto('num_prov_gob', 'Núm. proveedor gobierno')}
            </div>

            <BeneficiarioPicker
                label="Representante legal"
                value={form.data.representante_id as number | null}
                valueLabel={nombres.representante}
                error={form.errors.representante_id}
                onChange={(v) => form.setData('representante_id', v)}
            />

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
                onChange={(patch) => form.setData({ ...form.data, ...patch })}
            />

            <div className="grid gap-4 sm:grid-cols-3">
                {texto('telefono', 'Teléfono')}
                {texto('celular', 'Celular')}
                {texto('correo', 'Correo')}
            </div>
        </CrudFormDialog>
    );
}
