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

/** Las localidades traen además su sección electoral; el resto no. */
type Opcion = { id: number; nombre: string; seccion?: number | null };

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
    /**
     * Avisa la sección electoral de la localidad elegida. Sólo la usa el
     * directorio; en el legacy la sección se copia de la localidad y el campo
     * se muestra en sólo lectura.
     */
    onSeccion?: (seccion: number | null) => void;
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
    onSeccion,
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

    // Radix emite cadena vacía cuando limpia un valor controlado que todavía
    // no tiene opción; sin este guardia `Number('')` lo convertía en 0 y el
    // campo quedaba en blanco al abrir una edición.
    const aId = (v: string): number | null =>
        v === SIN || v === '' ? null : Number(v);

    const cambiarEstado = (v: string) => {
        estadoRef.current = aId(v);
        onChange({
            estado_id: estadoRef.current,
            municipio_id: null,
            localidad_id: null,
        });
        onSeccion?.(null);
    };

    const cambiarMunicipio = (v: string) => {
        municipioRef.current = aId(v);
        onChange({ municipio_id: municipioRef.current, localidad_id: null });
        onSeccion?.(null);
    };

    const cambiarLocalidad = (v: string) => {
        const id = aId(v);
        onChange({ localidad_id: id });
        onSeccion?.(localidades.find((l) => l.id === id)?.seccion ?? null);
    };

    /**
     * El valor actual siempre tiene opción, aunque la lista no haya cargado:
     * si falta, Radix descarta el valor y la edición se abre en blanco.
     */
    const conActual = (
        lista: Opcion[],
        id: number | null,
        nombre?: string | null,
    ): Opcion[] =>
        id && !lista.some((o) => o.id === id)
            ? [{ id, nombre: nombre ?? `#${id}` }, ...lista]
            : lista;

    const municipiosVisibles = conActual(
        municipios,
        value.municipio_id,
        municipioNombre,
    );
    const localidadesVisibles = conActual(
        localidades,
        value.localidad_id,
        localidadNombre,
    );

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
