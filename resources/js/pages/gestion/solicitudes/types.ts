export type Opcion = { id: number; nombre: string };
export type AreaOpcion = Opcion & { dependencia_id: number | null };

export type Solicitud = {
    id: number;
    folio: string | null;
    folio_sistema: string | null;
    solicitud: string | null;
    apoyo: string | null;
    cantidad: string | null;
    monto: string | null;
    desc_bene: string | null;
    bene_final: number | null;
    beneficiario_nombre: string | null;
    num_bene: string | null;
    status: number;
    status_label: string;
    control_administrativo: boolean;
    notificacion: boolean;
    prioridad: number | null;
    fecha_recepcion: string | null;
    fecha_comp: string | null;
    concepto_id: number | null;
    procedencia_id: number | null;
    unidad_medida_id: number | null;
    origen_recurso_id: number | null;
    folio_resp: string | null;
    fecha_resp: string | null;
    latitud: string | null;
    longitud: string | null;
    imagen: string | null;
    solicitante_tipo: 'beneficiario' | 'organizacion';
    solicitante_id: number | null;
    solicitante_nombre: string | null;
    municipio_resp_nombre: string | null;
    estado_resp_id: number | null;
    municipio_resp_id: number | null;
    localidad_resp_id: number | null;
    rubros: number[];
    sectores: number[];
    seguimientos_count: number;
};

export type Grupo = {
    id: number;
    tipo: 'beneficiario' | 'organizacion';
    nombre: string;
    localidad: string | null;
    telefonos: string | null;
    /** Cuántas tiene en total; `solicitudes` sólo trae la primera tanda. */
    solicitudes_total: number;
    solicitudes: Solicitud[];
};

export type Seguimiento = {
    id: number;
    estatus: number | null;
    estatus_label: string;
    estatus_resp_label: string | null;
    dependencia_id: number | null;
    dependencia: string | null;
    area_id: number | null;
    area: string | null;
    instruccion: string | null;
    comentario: string | null;
    respuesta: string | null;
    responsable: string | null;
    avance: number | null;
    autor: string | null;
    fecha: string | null;
    fecha_respuesta: string | null;
};

export type Verificacion = {
    id: number;
    fecha: string | null;
    atendido: number | null;
    satisfecho_label: string | null;
    comentario: string | null;
    autor: string | null;
};

export type Opciones = {
    estatus: Opcion[];
    conceptos: Opcion[];
    procedencias: Opcion[];
    rubros: Opcion[];
    sectores: Opcion[];
    estados: Opcion[];
    dependencias: Opcion[];
    areas: AreaOpcion[];
    unidadesMedida: Opcion[];
    origenesRecurso: Opcion[];
};

export type CamposBusqueda = {
    solicitante: boolean;
    telefono: boolean;
    celular: boolean;
    organizacion: boolean;
    tel_org: boolean;
    cel_org: boolean;
    solicitud: boolean;
    folio: boolean;
    beneficiario: boolean;
};

export type Filtros = {
    q: string;
    dependencia_id: number | null;
    estatus: number | null;
    campos: CamposBusqueda;
    /** Entrada directa desde el Directorio a un expediente concreto. */
    solicitante_tipo?: 'beneficiario' | 'organizacion' | null;
    solicitante_id?: number | null;
};

export type PaginacionBloque = {
    total: number;
    currentPage: number;
    lastPage: number;
    prevUrl: string | null;
    nextUrl: string | null;
} | null;

/**
 * Estatus de la solicitud en el legacy:
 * 0 CAPTURADA · 1 TURNADA · 2 NO APROBADA · 3 PARA RESOLVER
 * 4 RESPUESTA DE ÁREA · 5 ATENDIDA · 6 ATENCIÓN RÁPIDA
 *
 * De ahí salen las reglas de qué botón se muestra en cada fila.
 */
export const puedeEditar = (s: Solicitud) => s.status === 0;
export const puedeEliminar = (s: Solicitud) => [0, 1, 3].includes(s.status);
export const puedeSeguimiento = (s: Solicitud) => [0, 1, 3, 4].includes(s.status);
export const puedeAtender = (s: Solicitud) => [1, 3, 5].includes(s.status);
export const puedeVerificar = (s: Solicitud) => [5, 6].includes(s.status);
