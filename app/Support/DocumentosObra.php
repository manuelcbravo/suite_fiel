<?php

namespace App\Support;

/**
 * Los papeles del expediente de obra, por etapa.
 *
 * En el legacy cada uno era un par de columnas (`documento` / `obs_documento`)
 * dentro de la tabla `obra`; aquí son renglones de `tbl_obra_documentos` y esta
 * clase guarda la única parte que no vive en la base: cómo se llama cada papel.
 * Las etiquetas se tomaron una por una de las pantallas de `obrapublica/`.
 */
class DocumentosObra
{
    /** @var array<string, array<string, string>> */
    private const CATALOGO = [
        'ficha' => [
            'situacion_conobra' => 'Análisis de la situación con obra',
            'demanda_conobra' => 'Análisis de la demanda con obra',
            'oferta_conobra' => 'Análisis de la oferta con obra',
        ],
        'expediente' => [
            'validacion_campo' => 'Validación de campo',
            'carta_acep' => 'Acta de aceptación de la comunidad',
            'cedula_reg' => 'Cédula de registro, Información Básica y Marco Referencial',
            'resum_presup' => 'Resumen de Presupuesto',
            'presup_exped' => 'Presupuesto',
            'num_generadores' => 'Números Generadores',
            'explos_insumos' => 'Explosión de Insumos',
            'mems_calculo' => 'Memorias de Cálculo',
            'estudios_suelos' => 'Estudios Topográficos y Mecánica de Suelos',
            'mems_descrip' => 'Memorias Descriptivas',
            'proy_arqui' => 'Proyecto Arquitectónico',
            'proy_inge' => 'Proyecto de Ingeniería',
            'docs_comple' => 'Documentos Complementarios',
            'permisos' => 'Permisos y Licencias',
            'planos_ref' => 'Planos Referenciados',
            'normas_esp' => 'Normas y Especificaciones',
            'prec_unit_val' => 'Precios Unitarios Validados',
            'validacion_dep' => 'Validación de la Dependencia Normativa',
            'cal_inversion' => 'Calendario de inversión y de obra',
            'evidencia_foto' => 'Evidencia Fotográfica',
            'macrolocalizacion' => 'Macro Localización',
            'microlocalizacion' => 'Micro Localización',
            'acta_coplade' => 'Acta de Priorización de Obra en el COPLADE Municipal',
            'ajustes_doc' => 'Ajustes o modificaciones a la documentación',
            'carta_validacion' => 'Carta de validación',
            'oficio_envio' => 'Oficio de envío de expediente',
        ],
        'autorizacion' => [
            'acta_comite' => 'Acta de comité de obra',
            'prog_anual' => 'Programa anual de obra',
        ],
        'adjudicacion' => [
            'bases_concurso_licitacion' => 'Bases de concurso',
            'oficios_aceptacion_contrato' => 'Oficios de aceptación de empresas',
            'aceptacion_invitacion_contrato' => 'Escrito de aceptación para presentar propuesta',
            'propuesta_contrato' => 'Entrega de propuesta de obra por la persona física o moral',
            'junta_aclaraciones_contrato' => 'Acta de junta de aclaraciones',
            'visita_obra_contrato' => 'Acta de visita al sitio de la obra',
            'conocimiento_sitio_contrato' => 'Aceptación de que conoce el sitio de la obra',
            'invitacion_contato' => 'Invitación a instancias internas y externas al concurso',
            'prop_tecnicas_contrato' => 'Acta de recepción y apertura de proposiciones técnicas',
            'prop_economicas_contrato' => 'Acta de recepción y apertura de proposiciones económicas',
            'evaluacion_contrato' => 'Evaluación de las propuestas',
            'precios_unitarios_contrato' => 'Análisis de precios unitarios',
            'cuadro_contrato' => 'Cuadro comparativo',
            'dictamen_contrato' => 'Observaciones',
            'fallo_contrato' => 'Acta de fallo',
            'acta_adj_contrato' => 'Acta de adjudicación',
            'prop_firmada_contrato' => 'Propuesta ganadora firmada por los participantes',
            'aceptacion_contrato' => 'Escrito de aceptación por parte de la empresa ganadora',
            'doc_empresa_contrato' => 'Documentación de la empresa',
            'ganadora_contrato' => 'Propuesta técnica ganadora',
            'insumos_contrato' => 'Explosión de insumos',
            'programa_contrato' => 'Programa de ejecución de obra físico y financiero',
            'contrato_firmado' => 'Contrato firmado',
            'acta_comite_adj' => 'Acta constitutiva del comité',
            'dictamen_convenio' => 'Dictamen y justificación para la celebración del convenio',
            'convenio_ejecucion' => 'Convenio de concertación para ejecutar la obra',
            'nombramiento_supervisor' => 'Nombramiento del residente o supervisor de la presidencia',
            'dic_aprov_comite' => 'Dictamen de aprobación del comité',
            'supuesto_excepcion' => 'Fundamentación del supuesto de excepción',
            'motivos_excepcion' => 'Motivos, criterios o razones que justifiquen esta opción',
        ],
        'ejecucion' => [
            'conceptos_ejecutar' => 'Descripción, cantidad y volúmenes de conceptos de obra a ejecutar',
            'acta_copladem' => 'Acta del COPLADEM',
            'autorizacion_trabajos' => 'Áreas y servidores públicos responsables de la ejecución y autorización de los trabajos',
            'ejecucion_trabajos' => 'Programa de ejecución de los trabajos',
            'programa_materiales' => 'Programa de suministro y utilización de los materiales e insumos',
            'escrito_recursos' => 'Responsable de la ejecución de la obra',
            'programa_ejecucion' => 'Programa de ejecución de obra físico y financiero',
            'acta_spa' => 'Acuerdo firmado por Servidor Público Autorizado',
            'ejec_orden_inicio_obra' => 'Orden de inicio de obra',
            'entrega_inmueble' => 'Entrega física del inmueble',
            'designacion_residente' => 'Designación de residente de obra',
            'designacion_superintendente' => 'Designación de superintendente de obra',
            'bitacora_obra' => 'Bitácora de obra',
            'prog_ejec_contratista' => 'Programa de ejecución de obra (contratista)',
            'prog_ejec_obra' => 'Proyectos, planos, estudios, etc. para ejecutar la obra',
            'ejec_especificaciones' => 'Especificaciones de construcción',
            'informes_avances' => 'Informes periódicos de avance físico-financiero',
            'refrendos_convenios' => 'Refrendos de fianzas por convenios al contrato',
        ],
        'conclusion' => [
            'aviso_conclusion' => 'Aviso de conclusión de obra',
        ],
        'entrega' => [
            'fianza_vicios' => 'Fianza de vicios ocultos',
            'unitario_completo' => 'Expediente unitario completo',
            'rev_fisica_doc' => 'Revisión física y documental de la obra',
            'acta_extincion' => 'Acta de extinción de derechos y obligaciones',
        ],
    ];

    /**
     * Los documentos de una etapa, en el orden en que los pide el legacy.
     *
     * @return array<string, string>
     */
    public static function de(string $etapa): array
    {
        return self::CATALOGO[$etapa] ?? [];
    }

    /** @return array<string, array<string, string>> */
    public static function todos(): array
    {
        return self::CATALOGO;
    }

    /** Las claves válidas, para filtrar lo que llega del formulario. */
    public static function claves(string $etapa): array
    {
        return array_keys(self::de($etapa));
    }
}
