<?php

namespace Database\Seeders;

use App\Models\Denuncia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ETL de Seguridad (denuncias + detenidos) desde el respaldo de Actopan
 * (`hgo_actopan`). La geografía se remapea a los catálogos INEGI existentes
 * por clave natural. El denunciante/detenido son texto libre (no ligan al
 * directorio), por lo que no hay mezcla entre municipios.
 */
class SeguridadSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('plataforma.legacy_schema_seguridad', 'hgo_actopan');

        if (Denuncia::query()->exists()) {
            $this->command?->warn('Seguridad ya poblada; se omite el ETL.');

            return;
        }

        if (DB::selectOne("SELECT to_regclass('{$schema}.tbl_seguridad') IS NOT NULL AS existe")->existe !== true) {
            $this->command?->warn("Schema '{$schema}' sin tablas de seguridad; se omite. (Restaura el respaldo de Actopan.)");

            return;
        }

        $del = fn (string $a = '') => "CASE WHEN {$a}borrado = 1 THEN now() ELSE NULL END";

        // --- Catálogos ---------------------------------------------------
        DB::statement("INSERT INTO cat_seg_sectores (id, nombre, deleted_at, created_at, updated_at)
            SELECT id, nombre, {$del()}, now(), now() FROM {$schema}.tbl_seg_sector");
        DB::statement("INSERT INTO cat_origenes_denuncia (id, nombre, created_at, updated_at)
            SELECT id, nombre, now(), now() FROM {$schema}.tbl_origen_dnnc");
        DB::statement("INSERT INTO cat_tipos_incidencia (id, nombre, deleted_at, created_at, updated_at)
            SELECT id, nombre, {$del()}, now(), now() FROM {$schema}.cat_tipo_incidencia");
        DB::statement("INSERT INTO cat_niveles_violencia (id, nombre, created_at, updated_at)
            SELECT id, nombre, now(), now() FROM {$schema}.cat_violencia");
        foreach (['cat_seg_sectores', 'cat_origenes_denuncia', 'cat_tipos_incidencia', 'cat_niveles_violencia'] as $t) {
            $this->reancla($t);
        }

        // --- Denuncias ---------------------------------------------------
        DB::statement("INSERT INTO tbl_denuncias (
            id, anonimo, denunciante_nombre, denunciante_paterno, denunciante_materno,
            fecha_denuncia, hora_denuncia, origen_denuncia_id, denuncia, descripcion_situacion,
            tipo_incidencia_id, nivel_violencia_id, seg_sector_id,
            estado_id, municipio_id, localidad_id, latitud, longitud,
            atendido_por, fecha_atencion, hora_atencion, acciones, acuerdos_convenios, conclusion,
            asignado, vehiculo, clasificacion, turnado, con_atencion, con_termino, deleted_at, created_at, updated_at)
            SELECT s.id, COALESCE(s.anonimo,0) = 1, s.nombre_denunciante, s.paterno_denunciante, s.materno_denunciante,
                s.fecha_denuncia, s.hora_denuncia, od.id, s.denuncia, s.descripcion_situacion,
                ti.id, nv.id, ss.id,
                e.id, m.id, l.id, s.latitud, s.longitud,
                s.atendido_por, s.fecha_atencion, s.hora_atencion, s.acciones, s.acuerdos_convenios, s.conclusion,
                s.asignado, s.vehiculo, NULLIF(s.clasificacion,0), COALESCE(s.turnado,0),
                COALESCE(s.con_atencion,0) = 1, COALESCE(s.con_termino,0) = 1,
                {$del('s.')}, COALESCE(s.fecha_registro, now()), now()
            FROM {$schema}.tbl_seguridad s
            LEFT JOIN cat_origenes_denuncia od ON od.id = NULLIF(s.origen_denuncia,0)
            LEFT JOIN cat_tipos_incidencia ti ON ti.id = NULLIF(s.tipo_incidencia,0)
            LEFT JOIN cat_niveles_violencia nv ON nv.id = NULLIF(s.nivel_violencia,0)
            LEFT JOIN cat_seg_sectores ss ON ss.id = NULLIF(s.sector,0)
            LEFT JOIN cat_estados e ON e.id = NULLIF(s.id_edo_acceso,0)
            LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(s.id_edo_acceso,0) AND m.clave = NULLIF(s.id_mun_acceso,0)
            LEFT JOIN cat_localidades l ON l.estado_id = NULLIF(s.id_edo_acceso,0) AND l.municipio_id = m.id AND l.clave = NULLIF(s.localidad,0)");
        $this->reancla('tbl_denuncias');
        $this->command?->info(sprintf('  %-24s %d', 'tbl_denuncias', Denuncia::count()));

        // --- Personas detenidas (maestro) -------------------------------
        DB::statement("INSERT INTO tbl_personas_detenidas (id, nombre, paterno, materno, sexo, fecha_nac, nacionalidad, deleted_at, created_at, updated_at)
            SELECT id, nombre, paterno, materno, NULLIF(sexo,0), fecha_nac, nacionalidad, {$del()}, now(), now()
            FROM {$schema}.tbl_dtnd");
        $this->reancla('tbl_personas_detenidas');

        // --- Detenidos (evento de retención) ----------------------------
        DB::statement("INSERT INTO tbl_detenidos (
            id, denuncia_id, persona_id, nombre, paterno, materno, alias, edad, fecha_nac, sexo,
            nacionalidad, lugar_nac, estado_id, municipio_id, direccion, celular, telefono,
            lugar_retencion, fecha_retencion, padre_nombre, madre_nombre, motivo_retencion,
            descripcion_grafica, observaciones, foto, deleted_at, created_at, updated_at)
            SELECT d.id, den.id, per.id, d.nombre, d.paterno, d.materno, d.alias, NULLIF(d.edad,0), d.fecha_nac, NULLIF(d.sexo,0),
                d.nacionalidad, d.lugar_nac, e.id, m.id, d.direccion, d.celular, d.telefono,
                d.lugar_retencion, d.fecha_retencion, d.padre_nombre, d.madre_nombre, d.motivo_retencion,
                d.descripcion_grafica, d.observaciones, d.foto, {$del('d.')}, COALESCE(d.fecha_registro, now()), now()
            FROM {$schema}.tbl_detenido d
            LEFT JOIN tbl_denuncias den ON den.id = NULLIF(d.id_denuncia,0)
            LEFT JOIN tbl_personas_detenidas per ON per.id = NULLIF(d.id_dtnd,0)
            LEFT JOIN cat_estados e ON e.id = NULLIF(d.estado,0)
            LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(d.estado,0) AND m.clave = NULLIF(d.ciudad,0)");
        $this->reancla('tbl_detenidos');
        $this->command?->info(sprintf('  %-24s %d', 'tbl_detenidos', DB::table('tbl_detenidos')->count()));
    }

    private function reancla(string $tabla): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), COALESCE((SELECT MAX(id) FROM {$tabla}), 1))");
    }
}
