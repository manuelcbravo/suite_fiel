<?php

namespace Database\Seeders;

use App\Models\Beneficiario;
use App\Models\Organizacion;
use App\Models\Solicitud;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ETL de Gestión / Solicitudes (Módulo 3) desde el schema legacy.
 *
 * - Catálogos: rubros, conceptos, acciones.
 * - Solicitudes con solicitante polimórfico (beneficiario/organización),
 *   geografía de respuesta por clave natural y catálogos validados.
 * - Seguimientos (flujo de turnado/respuesta).
 * - Pivotes M2M rubro/sector, parseando las listas CSV legacy.
 */
class GestionSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if (Solicitud::query()->exists()) {
            $this->command?->warn('Gestión ya poblada; se omite el ETL.');

            return;
        }

        $del = fn (string $a = '') => "CASE WHEN {$a}borrado = 1 THEN now() ELSE NULL END";
        $tBene = Beneficiario::class;
        $tOrg = Organizacion::class;

        // --- Catálogos de gestión ---------------------------------------
        DB::statement("INSERT INTO cat_rubros (id, nombre, deleted_at, created_at, updated_at)
            SELECT id, nombre, {$del()}, now(), now() FROM {$schema}.rubro");
        DB::statement("INSERT INTO cat_conceptos (id, nombre, deleted_at, created_at, updated_at)
            SELECT id, nombre, {$del()}, now(), now() FROM {$schema}.concepto");
        DB::statement("INSERT INTO cat_acciones (id, nombre, created_at, updated_at)
            SELECT id, accion, now(), now() FROM {$schema}.tbl_accion");
        foreach (['cat_rubros', 'cat_conceptos', 'cat_acciones'] as $t) {
            $this->reancla($t);
        }
        $this->command?->info('  catálogos de gestión cargados');

        // --- Solicitudes -------------------------------------------------
        DB::statement("INSERT INTO tbl_solicitudes (
            id, folio, folio_sistema, solicitud, apoyo, desc_bene, cantidad, monto, num_bene, bene_final,
            solicitante_type, solicitante_id, concepto_id, procedencia_id, origen,
            status, prioridad, tipo, fecha_recepcion, fecha_comp,
            estado_resp_id, municipio_resp_id, localidad_resp_id, folio_resp, fecha_resp,
            imagen, latitud, longitud, deleted_at, created_at, updated_at)
            SELECT s.id, s.folio, s.folio_sistema, s.solicitud, s.apoyo, s.desc_bene, s.cantidad, s.monto, s.num_bene, NULLIF(s.bene_final,0),
                CASE WHEN s.tipo_solicitante=1 AND b.id IS NOT NULL THEN '{$tBene}'
                     WHEN s.tipo_solicitante=2 AND o.id IS NOT NULL THEN '{$tOrg}' END,
                CASE WHEN s.tipo_solicitante=1 AND b.id IS NOT NULL THEN b.id
                     WHEN s.tipo_solicitante=2 AND o.id IS NOT NULL THEN o.id END,
                cc.id, pr.id, s.origen,
                COALESCE(s.status,0), NULLIF(s.prioridad,0), NULLIF(s.tipo,0), s.fecha_recepcion, s.fecha_comp,
                e.id, m.id, l.id, s.folio_resp, s.fecha_resp,
                s.imagen, s.latitud, s.longitud, {$del('s.')}, COALESCE(s.fecha_captura, now()), COALESCE(s.fecha_captura, now())
            FROM {$schema}.solicitud s
            LEFT JOIN tbl_beneficiarios b ON b.id = s.solicitante_id AND s.tipo_solicitante = 1
            LEFT JOIN tbl_organizaciones o ON o.id = s.solicitante_id AND s.tipo_solicitante = 2
            LEFT JOIN cat_conceptos cc ON cc.id = NULLIF(s.concepto_id,0)
            LEFT JOIN cat_origenes_solicitud pr ON pr.id = NULLIF(s.procedencia_id,0)
            LEFT JOIN cat_estados e ON e.id = NULLIF(s.estado_resp,0)
            LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(s.estado_resp,0) AND m.clave = NULLIF(s.municipio_resp,0)
            LEFT JOIN cat_localidades l ON l.estado_id = NULLIF(s.estado_resp,0) AND l.municipio_id = m.id AND l.clave = NULLIF(s.localidad_resp,0)");
        $this->reancla('tbl_solicitudes');
        $this->command?->info(sprintf('  %-22s %d', 'tbl_solicitudes', Solicitud::count()));

        // --- Seguimientos ------------------------------------------------
        DB::statement("INSERT INTO tbl_seguimientos (
            id, solicitud_id, dependencia_id, area_id, estatus, instruccion, comentario, respuesta,
            responsable, fecha_respuesta, avance, estatus_resp, respuesta_de_id, deleted_at, created_at, updated_at)
            SELECT sg.id, sg.id_solicitud, d.id, a.id, NULLIF(sg.estatus,0), sg.instruccion, sg.comentario, sg.respuesta,
                sg.responsable, sg.fecha_respuesta, NULLIF(sg.avance,0), NULLIF(sg.estatus_resp,0), NULLIF(sg.respuesta_de,0),
                {$del('sg.')}, COALESCE(sg.fecha_creacion, now()), COALESCE(sg.fecha_creacion, now())
            FROM {$schema}.seguimiento sg
            JOIN tbl_solicitudes ts ON ts.id = sg.id_solicitud
            LEFT JOIN cat_dependencias d ON d.id = NULLIF(sg.id_dep_turnar,0)
            LEFT JOIN cat_areas a ON a.id = NULLIF(sg.id_area,0)");
        $this->reancla('tbl_seguimientos');
        $this->command?->info(sprintf('  %-22s %d', 'tbl_seguimientos', DB::table('tbl_seguimientos')->count()));

        // --- Pivotes M2M (parseo de listas CSV legacy) -------------------
        $this->pivote($schema, 'rubro_id', 'tbl_solicitud_rubro', 'rubro_id', 'cat_rubros');
        $this->pivote($schema, 'sector_id', 'tbl_solicitud_sector', 'sector_id', 'cat_sectores');
        $this->command?->info(sprintf('  %-22s %d', 'tbl_solicitud_rubro', DB::table('tbl_solicitud_rubro')->count()));
        $this->command?->info(sprintf('  %-22s %d', 'tbl_solicitud_sector', DB::table('tbl_solicitud_sector')->count()));
    }

    /**
     * Explota una columna CSV legacy de la solicitud hacia una tabla pivote,
     * validando que existan la solicitud y el catálogo destino.
     */
    private function pivote(string $schema, string $columnaCsv, string $tablaPivote, string $columnaId, string $catalogo): void
    {
        DB::statement("
            WITH tokens AS MATERIALIZED (
                SELECT s.id AS sid, trim(x) AS tok
                FROM {$schema}.solicitud s
                CROSS JOIN LATERAL regexp_split_to_table(COALESCE(s.{$columnaCsv}, ''), ',') AS x
            ),
            nums AS MATERIALIZED (
                SELECT sid, tok::int AS cid FROM tokens WHERE tok ~ '^[0-9]+$'
            )
            INSERT INTO {$tablaPivote} (solicitud_id, {$columnaId})
            SELECT DISTINCT n.sid, n.cid
            FROM nums n
            JOIN tbl_solicitudes ts ON ts.id = n.sid
            JOIN {$catalogo} c ON c.id = n.cid");
    }

    private function reancla(string $tabla): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), COALESCE((SELECT MAX(id) FROM {$tabla}), 1))");
    }
}
