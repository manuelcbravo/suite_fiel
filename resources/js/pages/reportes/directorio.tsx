import { Head } from '@inertiajs/react';
import { Contact } from 'lucide-react';
import { useState } from 'react';
import { ReporteShell } from '@/components/reporte-shell';
import type { Columna, Paginacion } from '@/components/reporte-shell';
import { SelectField } from '@/components/select-field';
import { UbicacionSelects } from '@/components/ubicacion-selects';

type Opcion = { id: number; nombre: string };
type Tipo = 'ciudadanos' | 'asociaciones' | 'proveedores';
type Filtros = {
    tipo: Tipo;
    estado_id: number | null;
    municipio_id: number | null;
    localidad_id: number | null;
    genero: number | null;
    sector_id: number | null;
    ocupacion_id: number | null;
    profesion_id: number | null;
    estado_civil_id: number | null;
};
type Opciones = {
    estados: Opcion[];
    sectores: Opcion[];
    sectoresOrg: Opcion[];
    ocupaciones: Opcion[];
    profesiones: Opcion[];
    estadosCiviles: Opcion[];
};

const TIPOS: Opcion[] = [
    { id: 1, nombre: 'Ciudadanos' },
    { id: 2, nombre: 'Asociaciones' },
    { id: 3, nombre: 'Proveedores' },
];
const GENEROS: Opcion[] = [
    { id: 1, nombre: 'Masculino' },
    { id: 2, nombre: 'Femenino' },
];
const TIPO_VALOR: Record<number, Tipo> = {
    1: 'ciudadanos',
    2: 'asociaciones',
    3: 'proveedores',
};
const TIPO_ID: Record<Tipo, number> = {
    ciudadanos: 1,
    asociaciones: 2,
    proveedores: 3,
};

const COLUMNAS: Record<Tipo, Columna[]> = {
    ciudadanos: [
        { key: 'nombre', header: 'Nombre' },
        { key: 'curp', header: 'CURP' },
        { key: 'genero', header: 'Género' },
        { key: 'celular', header: 'Celular' },
        { key: 'correo', header: 'Correo' },
        { key: 'ocupacion', header: 'Ocupación' },
        { key: 'sector', header: 'Sector' },
        { key: 'localidad', header: 'Localidad' },
        { key: 'municipio', header: 'Municipio' },
        { key: 'estado', header: 'Estado' },
    ],
    asociaciones: [
        { key: 'nombre', header: 'Nombre' },
        { key: 'tipo', header: 'Tipo' },
        { key: 'representante', header: 'Representante' },
        { key: 'celular', header: 'Celular' },
        { key: 'telefono', header: 'Teléfono' },
        { key: 'correo', header: 'Correo' },
        { key: 'municipio', header: 'Municipio' },
        { key: 'estado', header: 'Estado' },
    ],
    proveedores: [
        { key: 'nombre', header: 'Nombre' },
        { key: 'rfc', header: 'RFC' },
        { key: 'especialidad', header: 'Especialidad' },
        { key: 'celular', header: 'Celular' },
        { key: 'telefono', header: 'Teléfono' },
        { key: 'correo', header: 'Correo' },
        { key: 'municipio', header: 'Municipio' },
        { key: 'estado', header: 'Estado' },
    ],
};

export default function ReporteDirectorio({
    filtros,
    opciones,
    filas,
    paginacion,
}: {
    filtros: Filtros;
    opciones: Opciones;
    filas: Record<string, string | number | null>[];
    paginacion: Paginacion;
}) {
    const [tipo, setTipo] = useState<Tipo>(filtros.tipo);
    const [ubic, setUbic] = useState({
        estado_id: filtros.estado_id,
        municipio_id: filtros.municipio_id,
        localidad_id: filtros.localidad_id,
    });
    const [genero, setGenero] = useState<number | null>(filtros.genero);
    const [sector, setSector] = useState<number | null>(filtros.sector_id);
    const [ocupacion, setOcupacion] = useState<number | null>(
        filtros.ocupacion_id,
    );
    const [profesion, setProfesion] = useState<number | null>(
        filtros.profesion_id,
    );
    const [estadoCivil, setEstadoCivil] = useState<number | null>(
        filtros.estado_civil_id,
    );

    const params = {
        tipo,
        estado_id: ubic.estado_id,
        municipio_id: ubic.municipio_id,
        localidad_id: ubic.localidad_id,
        genero: tipo === 'ciudadanos' ? genero : null,
        sector_id:
            tipo === 'proveedores' ? null : sector,
        ocupacion_id: tipo === 'ciudadanos' ? ocupacion : null,
        profesion_id: tipo === 'ciudadanos' ? profesion : null,
        estado_civil_id: tipo === 'ciudadanos' ? estadoCivil : null,
    };

    return (
        <>
            <Head title="Reporte de directorio" />
            <ReporteShell
                title="Reporte de directorio"
                icon={Contact}
                pageUrl="/reportes/directorio"
                exportBase="/reportes/directorio.xlsx"
                params={params}
                onLimpiar={() => {
                    setUbic({
                        estado_id: null,
                        municipio_id: null,
                        localidad_id: null,
                    });
                    setGenero(null);
                    setSector(null);
                    setOcupacion(null);
                    setProfesion(null);
                    setEstadoCivil(null);
                }}
                columns={COLUMNAS[tipo]}
                filas={filas}
                paginacion={paginacion}
            >
                <SelectField
                    label="Directorio"
                    value={TIPO_ID[tipo]}
                    options={TIPOS}
                    onChange={(v) => setTipo(TIPO_VALOR[v ?? 1] ?? 'ciudadanos')}
                />
                {tipo === 'ciudadanos' && (
                    <>
                        <SelectField
                            label="Género"
                            value={genero}
                            options={GENEROS}
                            onChange={setGenero}
                        />
                        <SelectField
                            label="Sector"
                            value={sector}
                            options={opciones.sectores}
                            onChange={setSector}
                        />
                        <SelectField
                            label="Ocupación"
                            value={ocupacion}
                            options={opciones.ocupaciones}
                            onChange={setOcupacion}
                        />
                        <SelectField
                            label="Profesión"
                            value={profesion}
                            options={opciones.profesiones}
                            onChange={setProfesion}
                        />
                        <SelectField
                            label="Estado civil"
                            value={estadoCivil}
                            options={opciones.estadosCiviles}
                            onChange={setEstadoCivil}
                        />
                    </>
                )}
                {tipo === 'asociaciones' && (
                    <SelectField
                        label="Tipo de organización"
                        value={sector}
                        options={opciones.sectoresOrg}
                        onChange={setSector}
                    />
                )}
                <div className="sm:col-span-3 lg:col-span-4">
                    <UbicacionSelects
                        estados={opciones.estados}
                        value={ubic}
                        onChange={(patch) => setUbic({ ...ubic, ...patch })}
                    />
                </div>
            </ReporteShell>
        </>
    );
}

ReporteDirectorio.layout = {
    breadcrumbs: [
        { title: 'Reportes', href: '/reportes/directorio' },
        { title: 'Directorio', href: '/reportes/directorio' },
    ],
};
