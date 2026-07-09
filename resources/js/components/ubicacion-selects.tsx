import { useEffect, useRef, useState } from 'react';
import { Field, FieldError } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const SIN = '__none__';

type Opcion = { id: number; nombre: string };

type UbicacionValue = {
    estado_id: number | null;
    municipio_id: number | null;
    localidad_id: number | null;
};

type UbicacionSelectsProps = {
    estados: Opcion[];
    value: UbicacionValue;
    onChange: (patch: Partial<UbicacionValue>) => void;
    errors?: Partial<Record<keyof UbicacionValue, string>>;
    /** Nombres actuales (edición) para mostrar mientras cargan las listas. */
    municipioNombre?: string | null;
    localidadNombre?: string | null;
};

async function cargar(url: string): Promise<Opcion[]> {
    const respuesta = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!respuesta.ok) {
        return [];
    }

    return (await respuesta.json()) as Opcion[];
}

export function UbicacionSelects({
    estados,
    value,
    onChange,
    errors,
    municipioNombre,
    localidadNombre,
}: UbicacionSelectsProps) {
    const [municipios, setMunicipios] = useState<Opcion[]>([]);
    const [localidades, setLocalidades] = useState<Opcion[]>([]);
    const estadoRef = useRef(value.estado_id);
    const municipioRef = useRef(value.municipio_id);

    // Municipios cuando cambia el estado.
    useEffect(() => {
        let activo = true;
        const estadoId = value.estado_id;

        (async () => {
            const lista = estadoId
                ? await cargar(
                      `/directorio/ubicaciones/municipios?estado_id=${estadoId}`,
                  )
                : [];

            if (activo) {
                setMunicipios(lista);
            }
        })();

        return () => {
            activo = false;
        };
    }, [value.estado_id]);

    // Localidades cuando cambia el municipio.
    useEffect(() => {
        let activo = true;
        const municipioId = value.municipio_id;

        (async () => {
            const lista = municipioId
                ? await cargar(
                      `/directorio/ubicaciones/localidades?municipio_id=${municipioId}`,
                  )
                : [];

            if (activo) {
                setLocalidades(lista);
            }
        })();

        return () => {
            activo = false;
        };
    }, [value.municipio_id]);

    const cambiarEstado = (v: string) => {
        estadoRef.current = v === SIN ? null : Number(v);
        onChange({
            estado_id: estadoRef.current,
            municipio_id: null,
            localidad_id: null,
        });
    };

    const cambiarMunicipio = (v: string) => {
        municipioRef.current = v === SIN ? null : Number(v);
        onChange({ municipio_id: municipioRef.current, localidad_id: null });
    };

    const cambiarLocalidad = (v: string) => {
        onChange({ localidad_id: v === SIN ? null : Number(v) });
    };

    // Si la lista aún no carga en edición, muestra el nombre actual como opción.
    const municipiosVisibles =
        municipios.length === 0 && value.municipio_id && municipioNombre
            ? [{ id: value.municipio_id, nombre: municipioNombre }]
            : municipios;
    const localidadesVisibles =
        localidades.length === 0 && value.localidad_id && localidadNombre
            ? [{ id: value.localidad_id, nombre: localidadNombre }]
            : localidades;

    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <Field>
                <Label>Estado</Label>
                <Select
                    value={value.estado_id ? String(value.estado_id) : SIN}
                    onValueChange={cambiarEstado}
                >
                    <SelectTrigger
                        className="w-full"
                        aria-invalid={Boolean(errors?.estado_id)}
                    >
                        <SelectValue placeholder="Selecciona..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={SIN}>— Ninguno —</SelectItem>
                        {estados.map((e) => (
                            <SelectItem key={e.id} value={String(e.id)}>
                                {e.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors?.estado_id && (
                    <FieldError>{errors.estado_id}</FieldError>
                )}
            </Field>

            <Field>
                <Label>Municipio</Label>
                <Select
                    value={
                        value.municipio_id ? String(value.municipio_id) : SIN
                    }
                    onValueChange={cambiarMunicipio}
                    disabled={!value.estado_id}
                >
                    <SelectTrigger
                        className="w-full"
                        aria-invalid={Boolean(errors?.municipio_id)}
                    >
                        <SelectValue placeholder="Selecciona..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={SIN}>— Ninguno —</SelectItem>
                        {municipiosVisibles.map((m) => (
                            <SelectItem key={m.id} value={String(m.id)}>
                                {m.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors?.municipio_id && (
                    <FieldError>{errors.municipio_id}</FieldError>
                )}
            </Field>

            <Field>
                <Label>Localidad</Label>
                <Select
                    value={
                        value.localidad_id ? String(value.localidad_id) : SIN
                    }
                    onValueChange={cambiarLocalidad}
                    disabled={!value.municipio_id}
                >
                    <SelectTrigger
                        className="w-full"
                        aria-invalid={Boolean(errors?.localidad_id)}
                    >
                        <SelectValue placeholder="Selecciona..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={SIN}>— Ninguna —</SelectItem>
                        {localidadesVisibles.map((l) => (
                            <SelectItem key={l.id} value={String(l.id)}>
                                {l.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors?.localidad_id && (
                    <FieldError>{errors.localidad_id}</FieldError>
                )}
            </Field>
        </div>
    );
}
