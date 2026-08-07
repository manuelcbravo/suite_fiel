export type Opcion = { id: number; nombre: string };

/** Las tres pestañas del directorio legacy. */
export type Pestana = 'individuos' | 'organizaciones' | 'proveedores';

/** El tipo con el que viajan comentarios y fotos en las rutas existentes. */
export type TipoEntidad = 'beneficiarios' | 'organizaciones' | 'proveedores';

/**
 * Lo que necesita una tarjeta del directorio. Los tres tipos se pintan igual,
 * sólo cambia qué se pone en cada renglón.
 */
export type Ficha = {
    id: number;
    tipo: TipoEntidad;
    titulo: string;
    subtitulo: string | null;
    etiqueta: string | null;
    ubicacion: string | null;
    /** Si es false ni siquiera se intenta cargar la imagen. */
    tiene_foto: boolean;
    celular: string | null;
    telefono: string | null;
    correo: string | null;
    /** Pares etiqueta → valor que se muestran al abrir la ficha. */
    datos: Record<string, string | null>;
};

export type CamposBusqueda = {
    nombres: boolean;
    comentarios: boolean;
    trabajo: boolean;
};

export type Filtros = {
    q: string;
    campos: CamposBusqueda;
    estado_id: number | null;
    municipio_id: number | null;
    localidad_id: number | null;
    profesion_id: number | null;
    sector_id: number | null;
    genero: number | null;
};

export type PaginacionBloque = {
    total: number;
    currentPage: number;
    lastPage: number;
    prevUrl: string | null;
    nextUrl: string | null;
} | null;

export type Opciones = {
    estados: Opcion[];
    sectores: Opcion[];
    filiaciones: Opcion[];
    antagonicos: Opcion[];
    lideres: Opcion[];
    influencias: Opcion[];
    sectoresOrganizacion: Opcion[];
    ocupaciones: Opcion[];
    profesiones: Opcion[];
    estadosCiviles: Opcion[];
};

export const GENEROS: Opcion[] = [
    { id: 1, nombre: 'Masculino' },
    { id: 2, nombre: 'Femenino' },
];

export const TIPOS_PERSONA: Opcion[] = [
    { id: 1, nombre: 'Persona física' },
    { id: 2, nombre: 'Persona moral' },
];
