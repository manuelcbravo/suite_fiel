<?php

namespace Database\Seeders;

use App\Models\CatEstado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ETL de catálogos (Módulo 0) desde el schema legacy hacia las tablas nuevas.
 *
 * El schema legacy vive en la MISMA base de datos PostgreSQL, por lo que la
 * migración se hace con `INSERT ... SELECT` cross-schema. Los `LEFT JOIN`
 * contra las tablas ya cargadas anulan las FKs huérfanas (0 / inexistentes)
 * para no violar las restricciones. Se conservan los IDs legacy (salvo
 * municipios/localidades, con id surrogate + clave natural).
 *
 * Configurable vía SUITE_LEGACY_SCHEMA en el .env (default: hgo_pachuca).
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if (CatEstado::query()->exists()) {
            $this->command?->warn('Catálogos ya poblados; se omite el ETL. Vacía las tablas para recargar.');

            return;
        }

        // borrado (int 0/1) -> deleted_at.
        $del = fn (string $alias = '') => "CASE WHEN {$alias}borrado = 1 THEN now() ELSE NULL END";

        // Orden por dependencias: padres primero.
        $cargas = [
            'cat_estados' => "INSERT INTO cat_estados (id, nombre, siglas, created_at, updated_at)
                SELECT id, estado, siglas, now(), now() FROM {$schema}.tbl_estado",

            // Municipio: id surrogate; la clave legacy (única por estado) va en `clave`.
            'cat_municipios' => "INSERT INTO cat_municipios (clave, nombre, estado_id, latitud, longitud, created_at, updated_at)
                SELECT s.id, s.municipio, e.id, s.latitud, s.longitud, now(), now()
                FROM {$schema}.tbl_municipio s
                LEFT JOIN cat_estados e ON e.id = NULLIF(s.id_estado, 0)",

            'cat_tipos_localidad' => "INSERT INTO cat_tipos_localidad (id, nombre, created_at, updated_at)
                SELECT id, tipo, now(), now() FROM {$schema}.cat_tipo_localidad",

            // Localidad: id surrogate; se resuelve el municipio por clave natural (estado, clave).
            'cat_localidades' => "INSERT INTO cat_localidades (clave, nombre, municipio_id, estado_id, tipo_localidad_id, cp, clave_ine, deleted_at, created_at, updated_at)
                SELECT s.id, s.nombre, m.id, e.id, NULLIF(s.tipo, 0), s.cp, s.id_localidad_ine::text, {$del('s.')}, now(), now()
                FROM {$schema}.tbl_localidad s
                LEFT JOIN cat_estados e ON e.id = NULLIF(s.id_estado, 0)
                LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(s.id_estado, 0) AND m.clave = NULLIF(s.id_municipio, 0)",

            'cat_ocupaciones' => "INSERT INTO cat_ocupaciones (id, nombre, deleted_at, created_at, updated_at)
                SELECT id, nombre, {$del()}, now(), now() FROM {$schema}.tbl_ocupacion",

            'cat_profesiones' => "INSERT INTO cat_profesiones (id, nombre, abreviatura, created_at, updated_at)
                SELECT id, nombre, abreviatura, now(), now() FROM {$schema}.tbl_profesion",

            'cat_estados_civiles' => "INSERT INTO cat_estados_civiles (id, nombre, created_at, updated_at)
                SELECT id, nombre, now(), now() FROM {$schema}.cat_estado_civil",

            'cat_sectores' => "INSERT INTO cat_sectores (id, nombre, deleted_at, created_at, updated_at)
                SELECT id, nombre, {$del()}, now(), now() FROM {$schema}.sector",

            'cat_sectores_organizacion' => "INSERT INTO cat_sectores_organizacion (id, nombre, deleted_at, created_at, updated_at)
                SELECT id, nombre, {$del()}, now(), now() FROM {$schema}.cat_sectores_org",

            'cat_unidades_medida' => "INSERT INTO cat_unidades_medida (id, nombre, created_at, updated_at)
                SELECT id, tipo, now(), now() FROM {$schema}.tbl_tipo",

            'cat_origenes_solicitud' => "INSERT INTO cat_origenes_solicitud (id, nombre, created_at, updated_at)
                SELECT id, nombre, now(), now() FROM {$schema}.tbl_origen_sol",

            'cat_origenes_recurso' => "INSERT INTO cat_origenes_recurso (id, nombre, deleted_at, created_at, updated_at)
                SELECT id, nombre, {$del()}, now(), now() FROM {$schema}.tbl_origen_recurso",

            'cat_dependencias' => "INSERT INTO cat_dependencias (id, nombre, responsable, deleted_at, created_at, updated_at)
                SELECT id, nombre, responsable, {$del()}, now(), COALESCE(ultima_update, now()) FROM {$schema}.tbl_dependencias_mun",

            'cat_areas' => "INSERT INTO cat_areas (id, nombre, dependencia_id, deleted_at, created_at, updated_at)
                SELECT s.id, s.nombre, d.id, {$del('s.')}, now(), now()
                FROM {$schema}.tbl_area_mun s
                LEFT JOIN cat_dependencias d ON d.id = NULLIF(s.id_dep, 0)",

            'cat_ejes' => "INSERT INTO cat_ejes (id, nombre, estado_id, municipio_id, deleted_at, created_at, updated_at)
                SELECT s.id, s.eje, e.id, m.id, {$del('s.')}, now(), now()
                FROM {$schema}.tbl_eje s
                LEFT JOIN cat_estados e ON e.id = NULLIF(s.id_estado, 0)
                LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(s.id_estado, 0) AND m.clave = NULLIF(s.id_municipio, 0)",

            'cat_subejes' => "INSERT INTO cat_subejes (id, nombre, eje_id, estado_id, municipio_id, deleted_at, created_at, updated_at)
                SELECT s.id, s.subeje, ej.id, e.id, m.id, {$del('s.')}, now(), now()
                FROM {$schema}.tbl_subeje s
                LEFT JOIN cat_ejes ej ON ej.id = NULLIF(s.id_eje, 0)
                LEFT JOIN cat_estados e ON e.id = NULLIF(s.id_estado, 0)
                LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(s.id_estado, 0) AND m.clave = NULLIF(s.id_municipio, 0)",
        ];

        foreach ($cargas as $tabla => $sql) {
            DB::statement($sql);
            // Reancla la secuencia al MAX(id) para que los nuevos registros no colisionen.
            DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), COALESCE((SELECT MAX(id) FROM {$tabla}), 1))");
            $this->command?->info(sprintf('  %-28s %d', $tabla, DB::table($tabla)->count()));
        }
    }
}
