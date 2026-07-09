<?php

namespace Database\Seeders;

use App\Models\Beneficiario;
use App\Models\Organizacion;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ETL del Directorio (Módulo 2) desde el schema legacy.
 *
 * Orden: beneficiarios → organizaciones → proveedores → comentarios.
 * Se conservan los IDs legacy de las entidades (organización y comentarios
 * los referencian). La geografía se resuelve por clave natural contra los
 * catálogos `cat_*`. Se omiten campos electorales/tenancy.
 */
class DirectorioSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if (Beneficiario::query()->exists()) {
            $this->command?->warn('Directorio ya poblado; se omite el ETL.');

            return;
        }

        $del = fn (string $a = '') => "CASE WHEN {$a}borrado = 1 THEN now() ELSE NULL END";

        // --- Beneficiarios -----------------------------------------------
        DB::statement("INSERT INTO tbl_beneficiarios (
            id, nombre, paterno, materno, alias, curp, genero, nacimiento, tipo, estado_civil_id,
            calle, num_ext, num_int, colonia, cp, estado_id, municipio_id, localidad_id,
            telefono, celular, celular2, correo, correo2, facebook, twitter,
            empresa, puesto, tel_empresa, ocupacion_id, profesion_id, ocupacion_texto,
            sector_id, grupo, vinculo_municipal, vinculo_estatal, vinculo_federal,
            asist_nombre, asist_movil, asist_correo, conyuge_nombre, conyuge_movil, conyuge_nacimiento,
            foto, estatus, deleted_at, created_at, updated_at)
            SELECT b.id, b.nombre, b.paterno, b.materno, b.alias, b.curp, NULLIF(b.genero,0), b.nacimiento,
                NULLIF(b.tipo_id,0), ec.id,
                b.calle, b.num_ext, b.num_int, b.colonia, b.cp, e.id, m.id, l.id,
                b.telefono, b.celular, b.movil2, b.correo, b.correo2, b.facebook, b.twitter,
                b.empresa, b.puesto, b.tel_empresa, oc.id, pr.id, b.ocupacion,
                sc.id, b.grupo, b.vinculo_municipal, b.vinculo_estatal, b.vinculo_federal,
                b.asist_nombre, b.asist_movil, b.asist_correo, b.conyug_nombre, b.conyug_movil, b.conyug_nacimiento,
                b.foto, b.estatus, {$del('b.')}, COALESCE(b.ultima_update, now()), COALESCE(b.ultima_update, now())
            FROM {$schema}.beneficiario b
            LEFT JOIN cat_estados_civiles ec ON ec.id = NULLIF(b.edo_civil, 0)
            LEFT JOIN cat_ocupaciones oc ON oc.id = NULLIF(b.id_ocupacion, 0)
            LEFT JOIN cat_profesiones pr ON pr.id = NULLIF(b.id_profesion, 0)
            LEFT JOIN cat_sectores sc ON sc.id = NULLIF(b.sector_id, 0)
            LEFT JOIN cat_estados e ON e.id = NULLIF(b.id_estado, 0)
            LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(b.id_estado, 0) AND m.clave = NULLIF(b.id_municipio, 0)
            LEFT JOIN cat_localidades l ON l.estado_id = NULLIF(b.id_estado, 0) AND l.municipio_id = m.id AND l.clave = NULLIF(b.id_localidad, 0)");
        $this->reancla('tbl_beneficiarios');
        $this->command?->info(sprintf('  %-22s %d', 'tbl_beneficiarios', Beneficiario::count()));

        // --- Organizaciones ----------------------------------------------
        DB::statement("INSERT INTO tbl_organizaciones (
            id, nombre, tipo, sector_organizacion_id, representante_id,
            calle, num_ext, num_int, colonia, cp, estado_id, municipio_id, localidad_id,
            telefono, celular, correo, foto, deleted_at, created_at, updated_at)
            SELECT o.id, o.nombre, NULLIF(o.tipo,0), so.id, rb.id,
                o.calle, o.num_ext, o.num_int, o.colonia, o.cp, e.id, m.id, l.id,
                o.telefono, o.celular, o.correo, o.foto, {$del('o.')}, now(), now()
            FROM {$schema}.organizacion o
            LEFT JOIN cat_sectores_organizacion so ON so.id = NULLIF(o.id_sector, 0)
            LEFT JOIN tbl_beneficiarios rb ON rb.id = NULLIF(o.id_representante, 0)
            LEFT JOIN cat_estados e ON e.id = NULLIF(o.id_estado, 0)
            LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(o.id_estado, 0) AND m.clave = NULLIF(o.id_municipio, 0)
            LEFT JOIN cat_localidades l ON l.estado_id = NULLIF(o.id_estado, 0) AND l.municipio_id = m.id AND l.clave = NULLIF(o.id_localidad, 0)");
        $this->reancla('tbl_organizaciones');
        $this->command?->info(sprintf('  %-22s %d', 'tbl_organizaciones', Organizacion::count()));

        // --- Proveedores -------------------------------------------------
        DB::statement("INSERT INTO tbl_proveedores (
            id, nombre, rfc, rep_legal, especialidad, tipo, calificacion, num_prov_gob,
            calle, num_ext, num_int, colonia, cp, estado_id, municipio_id, localidad_id,
            telefono, celular, correo, foto, deleted_at, created_at, updated_at)
            SELECT p.id, p.nombre, p.rfc, p.rep_legal, p.especialidad, NULLIF(p.tipo,0), NULLIF(p.calificacion,0), p.num_prov_gob,
                p.calle, p.num_ext, p.num_int, p.colonia, p.cp, e.id, m.id, l.id,
                p.telefono, p.celular, p.correo, p.foto, {$del('p.')}, now(), now()
            FROM {$schema}.proveedor p
            LEFT JOIN cat_estados e ON e.id = NULLIF(p.id_estado, 0)
            LEFT JOIN cat_municipios m ON m.estado_id = NULLIF(p.id_estado, 0) AND m.clave = NULLIF(p.id_municipio, 0)
            LEFT JOIN cat_localidades l ON l.estado_id = NULLIF(p.id_estado, 0) AND l.municipio_id = m.id AND l.clave = NULLIF(p.id_localidad, 0)");
        $this->reancla('tbl_proveedores');
        $this->command?->info(sprintf('  %-22s %d', 'tbl_proveedores', Proveedor::count()));

        // --- Comentarios (polimórficos) ----------------------------------
        $tBene = Beneficiario::class;
        $tOrg = Organizacion::class;
        $tProv = Proveedor::class;

        DB::statement("INSERT INTO tbl_comentarios (comentable_type, comentable_id, comentario, tipo, quien, deleted_at, created_at, updated_at)
            SELECT '{$tBene}', c.id_beneficiario, c.comentario, NULLIF(c.tipo,0), NULL, {$del('c.')}, COALESCE(c.fecha_update, now()), COALESCE(c.fecha_update, now())
            FROM {$schema}.tbl_comentario_bene c
            JOIN tbl_beneficiarios b ON b.id = c.id_beneficiario");

        DB::statement("INSERT INTO tbl_comentarios (comentable_type, comentable_id, comentario, tipo, quien, deleted_at, created_at, updated_at)
            SELECT '{$tOrg}', c.id_organizacion, c.comentario, NULLIF(c.tipo,0), NULLIF(c.quien,0), {$del('c.')}, COALESCE(c.fecha_update, now()), COALESCE(c.fecha_update, now())
            FROM {$schema}.tbl_comentario_org c
            JOIN tbl_organizaciones o ON o.id = c.id_organizacion");

        DB::statement("INSERT INTO tbl_comentarios (comentable_type, comentable_id, comentario, tipo, quien, deleted_at, created_at, updated_at)
            SELECT '{$tProv}', c.id_prov, c.comentario, NULL, NULL, {$del('c.')}, COALESCE(c.fecha_update, now()), COALESCE(c.fecha_update, now())
            FROM {$schema}.tbl_comentario_prov c
            JOIN tbl_proveedores p ON p.id = c.id_prov");

        $this->command?->info(sprintf('  %-22s %d', 'tbl_comentarios', DB::table('tbl_comentarios')->count()));
    }

    private function reancla(string $tabla): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), COALESCE((SELECT MAX(id) FROM {$tabla}), 1))");
    }
}
