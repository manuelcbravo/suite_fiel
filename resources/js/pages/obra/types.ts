export type Opcion = { id: number; nombre: string };
export type Subeje = { id: number; nombre: string; eje_id: number | null };

export type Opciones = {
    unidades: Opcion[];
    conceptos: Opcion[];
    sectores: Opcion[];
    estados: Opcion[];
    pmdEjes: Opcion[];
    pmdSubejes: Subeje[];
    pedEjes: Opcion[];
    pedSubejes: Subeje[];
};

/** La ficha técnica tal como viaja entre el formulario y el servidor. */
export type Ficha = {
    id: number | null;
    solicitud_id: number | null;
    num_obra: string;
    nombre: string;
    unidad_medida_id: number | null;
    concepto_id: number | null;
    sector_id: number | null;
    dependencia: string;
    prop_financ: string;
    monto_inv: string;
    num_benef: string;
    descripcion: string;
    metas: string;
    calle_num: string;
    localidad_id: number | null;
    referencia_mapa: string;
    latitud: string;
    longitud: string;
    col_beneficiados: string;
    pmd_eje_id: number | null;
    pmd_subeje_id: number | null;
    ped_eje_id: number | null;
    ped_subeje_id: number | null;
    analisis_problematica: string;
    analisis_sinobra: string;
    alternativa_solucion: string;
    asp_tecnicos: string;
    asp_ambientales: string;
    asp_legales: string;
    asp_sociales: string;
    rentab_social: string;
    impacto_social: string;
    descrip_renta_impac: string;
    factibilidad: string;
    conclusion: string;
    ficha_completo: boolean;
};

export const FICHA_VACIA: Ficha = {
    id: null,
    solicitud_id: null,
    num_obra: '',
    nombre: '',
    unidad_medida_id: null,
    concepto_id: null,
    sector_id: null,
    dependencia: '',
    prop_financ: '',
    monto_inv: '',
    num_benef: '',
    descripcion: '',
    metas: '',
    calle_num: '',
    localidad_id: null,
    referencia_mapa: '',
    latitud: '',
    longitud: '',
    col_beneficiados: '',
    pmd_eje_id: null,
    pmd_subeje_id: null,
    ped_eje_id: null,
    ped_subeje_id: null,
    analisis_problematica: '',
    analisis_sinobra: '',
    alternativa_solucion: '',
    asp_tecnicos: '',
    asp_ambientales: '',
    asp_legales: '',
    asp_sociales: '',
    rentab_social: '',
    impacto_social: '',
    descrip_renta_impac: '',
    factibilidad: '',
    conclusion: '',
    ficha_completo: false,
};
