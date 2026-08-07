<?php

namespace App\Support;

/**
 * Los datos propios de cada etapa del expediente de obra: los que no son
 * acuses de papel (eso es `DocumentosObra`) sino números de oficio, fechas,
 * montos y nombres.
 *
 * Cada etapa dice en qué relación vive, y sus campos van agrupados como en las
 * pantallas del legacy. El tipo decide cómo se dibuja: `texto`, `fecha`,
 * `area` (varias líneas), `opciones` (catálogo fijo) y `empresa` (buscador de
 * proveedores del directorio).
 *
 * Conclusión y entrega comparten tabla (`tbl_obra_cierre`) porque en el legacy
 * son dos pantallas del mismo tramo final.
 */
class CamposObra
{
    /** Catálogos fijos que el legacy tenía como `<option>` en el HTML. */
    public const CATALOGOS = [
        'mod_ejecucion' => [
            1 => 'CONTRATO',
            2 => 'ADMINISTRACIÓN DIRECTA',
            3 => 'CONVENIO',
        ],
        'tipo_contratacion' => [
            1 => 'LICITACIÓN PÚBLICA',
            2 => 'INVITACIÓN A CUANDO MENOS 3 PERSONAS',
            5 => 'INVITACIÓN A CUANDO MENOS 5 PERSONAS',
            3 => 'ADJUDICACIÓN DIRECTA',
            4 => 'ADJUDICACIÓN POR EXCEPCIÓN',
        ],
        'tipo_bitacora' => [
            1 => 'CONVENCIONAL',
            2 => 'ELECTRÓNICA',
        ],
        'invitados_acto' => [
            1 => 'Empresa',
            2 => 'Secretaría de Contraloría',
            3 => 'Dependencia normativa',
            4 => 'Comité de obra',
            5 => 'Comunidad / Beneficiarios',
        ],
    ];

    /**
     * @var array<string, array{relacion: string, grupos: array<string, array<string, array{0: string, 1: string, 2?: string}>>}>
     */
    private const ETAPAS = [
        'autorizacion' => [
            'relacion' => 'autorizacion',
            'grupos' => [
                'Oficio de envío del expediente' => [
                    'num_oficio_envio' => ['Número de oficio', 'texto'],
                    'fecha_oficio_envio' => ['Fecha del oficio', 'fecha'],
                    'recep_oficio_envio' => ['Quién lo recibe', 'texto'],
                ],
                'Oficio de autorización' => [
                    'num_oficio_aut' => ['Número de oficio', 'texto'],
                    'fecha_oficio_aut' => ['Fecha del oficio', 'fecha'],
                    'recep_oficio_aut' => ['Quién lo recibe', 'texto'],
                    'num_obra_oficio_aut' => ['Número de obra asignado', 'texto'],
                ],
                'Cómo se va a ejecutar' => [
                    'mod_ejecucion' => ['Modalidad de ejecución', 'opciones', 'mod_ejecucion'],
                    'tipo_contratacion' => ['Tipo de contratación', 'opciones', 'tipo_contratacion'],
                ],
                'La obra como quedó autorizada' => [
                    'nombre_obra_aut' => ['Nombre autorizado', 'area'],
                    'inicio_obra_aut' => ['Inicio autorizado', 'fecha'],
                    'termino_obra_aut' => ['Término autorizado', 'fecha'],
                    'descrip_obra_aut' => ['Descripción autorizada', 'area'],
                ],
                'Metas' => [
                    'metas_unidad' => ['Unidad de medida', 'texto'],
                    'metas_cantidad' => ['Cantidad', 'texto'],
                    'metas_benef' => ['Beneficiarios', 'texto'],
                ],
                'Estructura financiera' => [
                    'ejercicio_fiscal' => ['Ejercicio fiscal', 'texto'],
                    'ramo' => ['Ramo', 'texto'],
                    'fondo' => ['Fondo', 'texto'],
                    'finan_prog' => ['Programa', 'texto'],
                    'finan_subprog' => ['Subprograma', 'texto'],
                    'finan_costo' => ['Costo', 'texto'],
                    'finan_estruc' => ['Estructura', 'texto'],
                    'presupuesto' => ['Presupuesto de la obra', 'texto'],
                    'anio_prog_anual' => ['Año del programa anual', 'texto'],
                ],
                'Aportaciones' => [
                    'aporte_fed' => ['Federal', 'texto'],
                    'aporte_est' => ['Estatal', 'texto'],
                    'aporte_mun' => ['Municipal', 'texto'],
                    'aporte_bene' => ['Beneficiarios', 'texto'],
                    'aporte_otr' => ['Otros', 'texto'],
                ],
            ],
        ],
        'adjudicacion' => [
            'relacion' => 'adjudicacion',
            'grupos' => [
                'Licitación' => [
                    'num_licitacion' => ['Número de licitación', 'texto'],
                    'convocatoria' => ['Convocatoria', 'texto'],
                    'num_convocatoria' => ['Número de convocatoria', 'texto'],
                    'fecha_convocatoria' => ['Fecha de la convocatoria', 'fecha'],
                    'medios_convocatoria' => ['Medios de publicación', 'area'],
                    'costo_bases' => ['Costo de las bases', 'texto'],
                ],
                'Contratista' => [
                    'empresa_id' => ['Proveedor del directorio', 'empresa'],
                    'nom_empresa' => ['Nombre de la empresa', 'texto'],
                    'rfc_empresa' => ['R.F.C.', 'texto'],
                    'rep_empresa' => ['Representante', 'texto'],
                    'reg_empresa' => ['Registro', 'texto'],
                    'dir_empresa' => ['Dirección', 'area'],
                ],
                'Contrato' => [
                    'num_oficio_contrato' => ['Oficio de contrato', 'texto'],
                    'fecha_oficio_contrato' => ['Fecha del oficio', 'fecha'],
                    'num_contrato' => ['Número de contrato', 'texto'],
                    'fecha_contrato' => ['Fecha del contrato', 'fecha'],
                    'monto_contrato' => ['Monto del contrato', 'texto'],
                    'monto_contrato_original' => ['Monto original', 'texto'],
                    'fecha_inicio_obra' => ['Inicio de obra', 'fecha'],
                    'fecha_termino_obra' => ['Término de obra', 'fecha'],
                    'fecha_inicio_original' => ['Inicio original', 'fecha'],
                    'fecha_termino_original' => ['Término original', 'fecha'],
                ],
                'Fianzas' => [
                    'num_fianza' => ['Número de fianza de anticipo', 'texto'],
                    'monto_fianza' => ['Monto de la fianza', 'texto'],
                    'num_cumplimiento' => ['Número de fianza de cumplimiento', 'texto'],
                    'monto_cumplimiento' => ['Monto de cumplimiento', 'texto'],
                ],
                'Convenio' => [
                    'num_convenio' => ['Número de convenio', 'texto'],
                    'fecha_convenio' => ['Fecha del convenio', 'fecha'],
                ],
            ],
        ],
        'ejecucion' => [
            'relacion' => 'ejecucion',
            'grupos' => [
                'Arranque' => [
                    'fecha_orden_inicio' => ['Orden de inicio', 'fecha'],
                    'fecha_termino_orden' => ['Término según la orden', 'fecha'],
                    'fecha_entrega_inmueble' => ['Entrega del inmueble', 'fecha'],
                    'fecha_inicio_real' => ['Inicio real', 'fecha'],
                    'fecha_termino_real' => ['Término real', 'fecha'],
                ],
                'Quién responde' => [
                    'responsable' => ['Responsable de la obra', 'texto'],
                    'residente' => ['Residente', 'texto'],
                    'superintendente' => ['Superintendente', 'texto'],
                ],
                'Bitácora' => [
                    'tipo_bitacora' => ['Tipo de bitácora', 'opciones', 'tipo_bitacora'],
                    'fecha_apertura_bitacora' => ['Apertura de la bitácora', 'fecha'],
                ],
                'Pagos' => [
                    'anticipo' => ['Anticipo', 'texto'],
                    'num_factura' => ['Número de factura', 'texto'],
                    'monto_factura' => ['Monto facturado', 'texto'],
                    'fecha_factura' => ['Fecha de la factura', 'fecha'],
                    'monto_pagado' => ['Monto pagado', 'texto'],
                    'fecha_pagado' => ['Fecha del pago', 'fecha'],
                ],
                'Observaciones' => [
                    'observaciones' => ['Observaciones de la ejecución', 'area'],
                ],
            ],
        ],
        'conclusion' => [
            'relacion' => 'cierre',
            'grupos' => [
                'Aviso de conclusión' => [
                    'fecha_aviso_conclusion' => ['Fecha del aviso', 'fecha'],
                    'revision_trabajos' => ['Revisión de los trabajos', 'texto'],
                ],
                'Entrega física y finiquito' => [
                    'acta_entrega_fisica' => ['Acta de entrega física', 'texto'],
                    'fecha_acta_entrega_fisica' => ['Fecha del acta', 'fecha'],
                    'finiquito' => ['Finiquito', 'texto'],
                ],
                'Observaciones' => [
                    'observaciones_conclusion' => ['Observaciones de la conclusión', 'area'],
                ],
            ],
        ],
        'entrega' => [
            'relacion' => 'cierre',
            'grupos' => [
                'Acto de entrega-recepción' => [
                    'fecha_invitacion_acto' => ['Fecha de la invitación', 'fecha'],
                    'invitados_acto' => ['A quién se invitó', 'multiple', 'invitados_acto'],
                    'fecha_rev_fisica_doc' => ['Revisión física y documental', 'fecha'],
                ],
                'Actas' => [
                    'acta_entrega_recepcion' => ['Acta de entrega-recepción', 'texto'],
                    'fecha_acta_entrega_recepcion' => ['Fecha del acta', 'fecha'],
                    'fecha_acta_extincion' => ['Acta de extinción de derechos', 'fecha'],
                ],
                'Fianza de vicios ocultos' => [
                    'fecha_fianza_vicios' => ['Fecha de la fianza', 'fecha'],
                ],
                'Observaciones' => [
                    'observacion_general' => ['Observación general', 'area'],
                    'observaciones_entrega' => ['Observaciones de la entrega', 'area'],
                ],
            ],
        ],
    ];

    /** La relación 1:1 donde viven los datos de la etapa; null si no tiene. */
    public static function relacion(string $etapa): ?string
    {
        return self::ETAPAS[$etapa]['relacion'] ?? null;
    }

    /** @return array<string, array<string, array{0: string, 1: string, 2?: string}>> */
    public static function grupos(string $etapa): array
    {
        return self::ETAPAS[$etapa]['grupos'] ?? [];
    }

    /**
     * Los campos de la etapa, aplanados, para leer y validar.
     *
     * @return array<string, array{0: string, 1: string, 2?: string}>
     */
    public static function campos(string $etapa): array
    {
        $salida = [];

        foreach (self::grupos($etapa) as $campos) {
            $salida += $campos;
        }

        return $salida;
    }
}
