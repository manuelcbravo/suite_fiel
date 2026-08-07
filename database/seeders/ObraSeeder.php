<?php

namespace Database\Seeders;

use App\Models\Obra;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ETL de Obra pública (Módulo 10) desde el schema legacy.
 *
 * Se conservan los IDs legacy porque las tablas hijas los referencian.
 * La tabla `obra` del legacy trae 313 columnas; aquí se reparte en el núcleo,
 * las cuatro tablas de etapa y `tbl_obra_documentos`, que recoge los 87 pares
 * `documento` / `obs_documento`.
 */
class ObraSeeder extends Seeder
{
    /**
     * A qué etapa pertenece cada papel del expediente. El orden es el mismo en
     * que están las columnas del legacy, que es como se agrupan sus pantallas.
     *
     * @var array<string, list<string>>
     */
    private const DOCUMENTOS = [
        'ficha' => [
            'situacion_conobra', 'demanda_conobra', 'oferta_conobra',
        ],
        'expediente' => [
            'validacion_campo', 'carta_acep', 'cedula_reg', 'resum_presup', 'presup_exped',
            'num_generadores', 'explos_insumos', 'mems_calculo', 'estudios_suelos',
            'mems_descrip', 'proy_arqui', 'proy_inge', 'docs_comple', 'permisos',
            'planos_ref', 'normas_esp', 'prec_unit_val', 'validacion_dep', 'cal_inversion',
            'evidencia_foto', 'macrolocalizacion', 'microlocalizacion', 'acta_coplade',
            'oficio_envio', 'ajustes_doc', 'carta_validacion',
        ],
        'autorizacion' => [
            'acta_comite', 'prog_anual',
        ],
        'adjudicacion' => [
            'bases_concurso_licitacion', 'oficios_aceptacion_contrato',
            'aceptacion_invitacion_contrato',
            'propuesta_contrato', 'junta_aclaraciones_contrato', 'visita_obra_contrato',
            'conocimiento_sitio_contrato', 'invitacion_contato', 'prop_tecnicas_contrato',
            'prop_economicas_contrato', 'evaluacion_contrato', 'precios_unitarios_contrato',
            'cuadro_contrato', 'dictamen_contrato', 'fallo_contrato', 'acta_adj_contrato',
            'prop_firmada_contrato', 'aceptacion_contrato', 'doc_empresa_contrato',
            'ganadora_contrato', 'insumos_contrato', 'programa_contrato', 'contrato_firmado',
            'acta_comite_adj', 'dictamen_convenio', 'convenio_ejecucion',
            'nombramiento_supervisor', 'dic_aprov_comite', 'supuesto_excepcion',
            'motivos_excepcion',
        ],
        'ejecucion' => [
            'conceptos_ejecutar', 'acta_copladem', 'autorizacion_trabajos',
            'ejecucion_trabajos', 'programa_materiales', 'escrito_recursos',
            'programa_ejecucion', 'acta_spa', 'ejec_orden_inicio_obra', 'entrega_inmueble',
            'designacion_residente', 'designacion_superintendente', 'bitacora_obra',
            'prog_ejec_contratista', 'prog_ejec_obra', 'ejec_especificaciones',
            'informes_avances', 'refrendos_convenios',
        ],
        'conclusion' => [
            'aviso_conclusion',
        ],
        'entrega' => [
            'fianza_vicios', 'unitario_completo', 'rev_fisica_doc',
            'acta_extincion',
        ],
    ];

    public function run(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if (Obra::query()->exists()) {
            $this->command?->warn('Obra pública ya poblada; se omite el ETL.');

            return;
        }

        $this->catalogos($schema);
        $this->obras($schema);
        $this->documentos($schema);
        $this->etapas($schema);
        $this->hijas($schema);

        $this->command?->info('Obras: '.Obra::query()->count().
            ' · documentos: '.DB::table('tbl_obra_documentos')->count().
            ' · estimaciones: '.DB::table('tbl_obra_estimaciones')->count());
    }

    private function catalogos(string $schema): void
    {
        // Los ejes/subejes del PMD los siembra `CatalogosSeeder`.
        DB::statement("INSERT INTO cat_ped_ejes (id, nombre, deleted_at, created_at, updated_at)
            SELECT id, eje, CASE WHEN borrado = 1 THEN now() END, now(), now()
            FROM {$schema}.ped_ejes WHERE eje IS NOT NULL");

        DB::statement("INSERT INTO cat_ped_subejes (id, nombre, eje_id, deleted_at, created_at, updated_at)
            SELECT s.id, s.subeje, e.id, CASE WHEN s.borrado = 1 THEN now() END, now(), now()
            FROM {$schema}.ped_subejes s
            LEFT JOIN {$schema}.ped_ejes e ON e.id = s.eje_id
            WHERE s.subeje IS NOT NULL");

        foreach (['cat_ped_ejes', 'cat_ped_subejes'] as $t) {
            $this->reiniciaSecuencia($t);
        }
    }

    private function obras(string $schema): void
    {
        $si = fn (string $c) => "CASE WHEN o.{$c} = 1 THEN true ELSE false END";

        DB::statement("INSERT INTO tbl_obras (
            id, solicitud_id, num_obra, nombre, unidad_medida_id, concepto_id, sector_id,
            dependencia, prop_financ, monto_inv, num_benef, descripcion, metas,
            calle_num, localidad_id, referencia_mapa, latitud, longitud, col_beneficiados,
            pmd_eje_id, pmd_subeje_id, ped_eje_id, ped_subeje_id,
            analisis_problematica, analisis_sinobra, alternativa_solucion,
            asp_tecnicos, asp_ambientales, asp_legales, asp_sociales,
            rentab_social, impacto_social, descrip_renta_impac, factibilidad, conclusion,
            ficha_completo, expediente_completo, autorizacion_completo, adjudicacion_completo,
            ejecucion_completo, conclusion_completo, entrega_completo,
            deleted_at, created_at, updated_at)
            SELECT o.id, s.id, o.num_obra, o.nombre_obra, t.id, c.id, sec.id,
                o.dependencia_resp, o.prop_financ, o.monto_inv, o.num_benef, o.descrip_obra, o.metas_obra,
                o.calle_num, loc.id, o.referencia_mapa, o.latitud_mapa, o.longitud_mapa, o.col_beneficiados,
                pe.id, ps.id, de.id, ds.id,
                o.analisis_problematica, o.analisis_sinobra, o.alternativa_solucion,
                o.asp_tecnicos_relev, o.asp_ambient_relev, o.asp_legales_relev, o.asp_sociales_relev,
                o.rentab_social, o.impacto_social, o.descrip_renta_impac, o.factibilidad, o.conclusion,
                {$si('ficha_completo')}, {$si('expediente_completo')}, {$si('autorizacion_completo')},
                {$si('adjudicacion_completo')}, {$si('ejecucion_completo')}, {$si('conclusion_completo')},
                {$si('entrega_completo')},
                CASE WHEN o.borrado = 1 THEN now() END, now(), now()
            FROM {$schema}.obra o
            LEFT JOIN tbl_solicitudes s ON s.id = o.id_solicitud
            LEFT JOIN cat_unidades_medida t ON t.id = NULLIF(o.tipo_obra_id, 0)
            LEFT JOIN cat_conceptos c ON c.id = o.categoria_obra_id
            LEFT JOIN cat_sectores sec ON sec.id = o.segmento_id
            LEFT JOIN cat_localidades loc ON loc.clave = NULLIF(o.colonia_id, 0)
            LEFT JOIN cat_ejes pe ON pe.id = o.pdm_eje
            LEFT JOIN cat_subejes ps ON ps.id = o.pdm_subeje
            LEFT JOIN cat_ped_ejes de ON de.id = o.ped_eje
            LEFT JOIN cat_ped_subejes ds ON ds.id = o.ped_subeje");

        $this->reiniciaSecuencia('tbl_obras');
    }

    /**
     * Los 87 pares `documento` / `obs_documento` se vuelven renglones. Se
     * omiten los que no traen ni acuse ni observación, para no crear 12,615
     * renglones vacíos.
     */
    private function documentos(string $schema): void
    {
        foreach (self::DOCUMENTOS as $etapa => $claves) {
            $selects = [];

            foreach ($claves as $clave) {
                // Se compara como texto porque el legacy mezcla tipos: casi
                // todas las columnas son varchar con "si"/"no", pero alguna
                // quedó como integer.
                $selects[] = "SELECT o.id AS obra_id, '{$etapa}' AS etapa, '{$clave}' AS clave,
                    CASE WHEN lower(o.{$clave}::text) IN ('si', '1') THEN true
                         WHEN lower(o.{$clave}::text) IN ('no', '0') THEN false END AS presente,
                    NULLIF(o.obs_{$clave}::text, '') AS observacion
                    FROM {$schema}.obra o
                    WHERE (o.{$clave} IS NOT NULL AND o.{$clave}::text <> '')
                       OR (o.obs_{$clave} IS NOT NULL AND o.obs_{$clave}::text <> '')";
            }

            DB::statement('INSERT INTO tbl_obra_documentos (obra_id, etapa, clave, presente, observacion, created_at, updated_at)
                SELECT d.obra_id, d.etapa, d.clave, d.presente, d.observacion, now(), now()
                FROM ('.implode(' UNION ALL ', $selects).') d
                JOIN tbl_obras t ON t.id = d.obra_id');
        }
    }

    /**
     * Fecha del legacy a `date`. Varias columnas que guardan fechas quedaron
     * como `varchar` con basura ("", "0000-00-00", texto suelto); lo que no
     * tenga forma de fecha se descarta en vez de reventar el ETL.
     */
    private function fecha(string $col): string
    {
        return "CASE WHEN {$col}::text ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}'
            AND {$col}::text NOT LIKE '0000%' THEN left({$col}::text, 10)::date END";
    }

    private function etapas(string $schema): void
    {
        $f = fn (string $c) => $this->fecha("o.{$c}");

        DB::statement("INSERT INTO tbl_obra_autorizacion (
            obra_id, num_oficio_envio, fecha_oficio_envio, recep_oficio_envio,
            num_oficio_aut, fecha_oficio_aut, recep_oficio_aut, num_obra_oficio_aut,
            mod_ejecucion, tipo_contratacion, nombre_obra_aut, inicio_obra_aut, termino_obra_aut,
            descrip_obra_aut, finan_prog, finan_subprog, finan_costo, finan_estruc,
            metas_unidad, metas_cantidad, metas_benef, ejercicio_fiscal, ramo, fondo,
            aporte_fed, aporte_est, aporte_mun, aporte_bene, aporte_otr, presupuesto,
            anio_prog_anual, created_at, updated_at)
            SELECT o.id, o.num_oficio_envio, {$f('fecha_oficio_envio')}, o.recep_oficio_envio,
                o.num_oficio_aut, {$f('fecha_oficio_aut')}, o.recep_oficio_aut, o.num_obra_oficio_aut,
                o.mod_ejecucion_obra, o.tipo_contratacion_obra, o.nombre_obra_aut,
                {$f('inicio_obra_aut')}, {$f('termino_obra_aut')}, o.descrip_obra_aut,
                o.finan_prog, o.finan_subprog, o.finan_costo, o.finan_estruc,
                o.metas_unidad, o.metas_cantidad, o.metas_benef, o.aut_ejercicio_fiscal,
                o.aut_ramo, o.aut_fondo, o.aut_fed, o.aut_est, o.aut_mun, o.aut_bene, o.aut_otr,
                o.presupuesto_obra, o.anio_prog_anual, now(), now()
            FROM {$schema}.obra o JOIN tbl_obras t ON t.id = o.id");

        DB::statement("INSERT INTO tbl_obra_adjudicacion (
            obra_id, num_licitacion, num_oficio_contrato, fecha_oficio_contrato,
            convocatoria, num_convocatoria, fecha_convocatoria, medios_convocatoria, costo_bases,
            empresa_id, nom_empresa, rfc_empresa, dir_empresa, rep_empresa, reg_empresa,
            num_fianza, monto_fianza, num_cumplimiento, monto_cumplimiento,
            num_contrato, fecha_contrato, monto_contrato, monto_contrato_original,
            fecha_inicio_obra, fecha_termino_obra, fecha_inicio_original, fecha_termino_original,
            num_convenio, fecha_convenio, created_at, updated_at)
            SELECT o.id, o.num_licitacion, o.num_oficio_contrato, {$f('fecha_oficio_contrato')},
                o.convocatoria_licitacion, o.num_convocatoria_licitacion, {$f('fecha_convocatoria_licitacion')},
                o.medios_convocatoria_licitacion, o.costo_bases_licitacion,
                p.id, o.nom_empresa_contrato, o.rfc_empresa_contrato, o.dir_empresa_contrato,
                o.rep_empresa_contrato, o.reg_empresa_contrato,
                o.num_fianza_contrato, o.monto_fianza_contrato, o.num_cumpli_contrato, o.monto_cumpli_contrato,
                o.num_contrato, {$f('fecha_contrato')}, o.monto_contrato, o.monto_contrato_original,
                {$f('fecha_inicio_obra')}, {$f('fecha_termino_obra')}, {$f('fecha_inicio_obra_original')},
                {$f('fecha_termino_obra_original')}, o.num_convenio_obra, {$f('fecha_convenio_obra')}, now(), now()
            FROM {$schema}.obra o
            JOIN tbl_obras t ON t.id = o.id
            LEFT JOIN tbl_proveedores p ON p.id = o.adj_empresa_id");

        DB::statement("INSERT INTO tbl_obra_ejecucion (
            obra_id, fecha_inicio_real, fecha_termino_real, responsable,
            fecha_orden_inicio, fecha_termino_orden, fecha_entrega_inmueble,
            fecha_apertura_bitacora, tipo_bitacora, residente, superintendente,
            observaciones, anticipo, num_factura, monto_factura, fecha_factura,
            monto_pagado, fecha_pagado, created_at, updated_at)
            SELECT o.id, {$f('fecha_inicio_obra_real')}, {$f('fecha_termino_obra_real')}, o.responsable_obra,
                {$f('ejec_fecha_inicio_obra')}, {$f('ejec_fecha_termino_obra')}, {$f('fecha_entrega_inmueble')},
                {$f('fecha_apertura_bitacora')}, o.tipo_bitacora_obra, o.residente_inicio_obra,
                o.superintendente_inicio_obra, o.observaciones_ejecucion, o.ejec_anticipo,
                o.eje_numero_factura, o.eje_monto_factura, {$f('eje_fecha_factura')},
                o.eje_monto_pagado, {$f('eje_fecha_pagado')}, now(), now()
            FROM {$schema}.obra o JOIN tbl_obras t ON t.id = o.id");

        DB::statement("INSERT INTO tbl_obra_cierre (
            obra_id, fecha_aviso_conclusion, revision_trabajos, acta_entrega_fisica,
            fecha_acta_entrega_fisica, finiquito, observaciones_conclusion,
            fecha_fianza_vicios, fecha_invitacion_acto, fecha_rev_fisica_doc,
            observacion_general, acta_entrega_recepcion, fecha_acta_entrega_recepcion,
            fecha_acta_extincion, observaciones_entrega, created_at, updated_at)
            SELECT o.id, {$f('fecha_aviso_conclusion')}, o.revision_trabajos, o.acta_entrega_fisica,
                {$f('fecha_acta_entrega_fisica')}, o.finiquito_obra, o.observaciones_conclusion,
                {$f('fecha_fianza_vicios')}, {$f('fecha_invitacion_acto')}, {$f('fecha_rev_fisica_doc')},
                o.observacion_general, o.acta_entrega_recepcion, {$f('fecha_acta_entrega_recepcion')},
                {$f('fecha_acta_extincion')}, o.observaciones_entrega, now(), now()
            FROM {$schema}.obra o JOIN tbl_obras t ON t.id = o.id");
    }

    private function hijas(string $schema): void
    {
        DB::statement("INSERT INTO tbl_obra_componentes (id, obra_id, componente, descripcion, cantidad, costo, total, created_at, updated_at)
            SELECT c.id, c.obra_id, c.componente, c.descripcion, c.cantidad, c.costo, c.total, now(), now()
            FROM {$schema}.obra_componentes c JOIN tbl_obras t ON t.id = c.obra_id");

        foreach (['beneficios', 'costos'] as $tabla) {
            DB::statement("INSERT INTO tbl_obra_{$tabla} (id, obra_id, tipo, descripcion, cuantificacion, valoracion, periodicidad, created_at, updated_at)
                SELECT x.id, x.obra_id, x.tipo, x.descripcion, x.cuantificacion, x.valoracion, x.periodicidad, now(), now()
                FROM {$schema}.obra_{$tabla} x JOIN tbl_obras t ON t.id = x.obra_id");

            $this->reiniciaSecuencia("tbl_obra_{$tabla}");
        }

        DB::statement("INSERT INTO tbl_obra_estimaciones (
            id, obra_id, num_estimacion, tipo, superintendente, residente, fecha_recepcion,
            periodo_inicio, periodo_termino, monto, calidad_obra, estatus, monto_aprobado,
            fecha_aprobacion, num_factura, fecha_factura, importe_pagado, fecha_pago,
            avance_fisico, avance_financiero, doc_obligatoria, completo, deleted_at, created_at, updated_at)
            SELECT e.id, e.obra_id, e.num_estimacion, NULLIF(e.tipo_id, 0), e.nom_superintendente,
                e.nom_residente, {$this->fecha('e.fecha_recep_est')}, {$this->fecha('e.periodo_inicio_est')}, {$this->fecha('e.periodo_termino_est')},
                e.monto_est, e.calidad_obra, e.estatus_est, e.monto_aprobado, {$this->fecha('e.fecha_aprobacion')},
                e.num_factura, {$this->fecha('e.fecha_factura')}, e.importe_pagado, {$this->fecha('e.fecha_pago')},
                e.avance_fisico, e.avance_financiero, NULLIF(e.doc_obligatoria, ''),
                CASE WHEN e.completo = 1 THEN true ELSE false END,
                CASE WHEN e.borrado = 1 THEN now() END, now(), now()
            FROM {$schema}.obra_estimaciones e JOIN tbl_obras t ON t.id = e.obra_id");

        $this->reiniciaSecuencia('tbl_obra_componentes');
        $this->reiniciaSecuencia('tbl_obra_estimaciones');
    }

    /** Deja la secuencia por encima del último id copiado del legacy. */
    private function reiniciaSecuencia(string $tabla): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'),
            COALESCE((SELECT MAX(id) FROM {$tabla}), 0) + 1, false)");
    }
}
