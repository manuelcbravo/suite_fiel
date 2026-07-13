<?php

namespace Database\Seeders;

use App\Models\CatTipoEvento;
use App\Models\Evento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ETL de Agenda (Módulo 4) desde el schema legacy.
 *
 * - Tipos de evento con color (se excluyen los de "Gira de Trabajo").
 * - Eventos: consolida `fechainicio`+`horainicio` (texto) en timestamps
 *   `inicio`/`fin`; convierte banderas int a boolean. Se excluyen los eventos
 *   de tipo gira y se descarta la geografía legacy (venía vacía).
 */
class AgendaSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if (CatTipoEvento::query()->exists()) {
            $this->command?->warn('Agenda ya poblada; se omite el ETL.');

            return;
        }

        $del = fn (string $a = '') => "CASE WHEN {$a}borrado = 1 THEN now() ELSE NULL END";

        // Tipos de evento (sin giras).
        DB::statement("INSERT INTO cat_tipos_evento (id, nombre, color, deleted_at, created_at, updated_at)
            SELECT id, nombre, color, {$del()}, now(), now()
            FROM {$schema}.cal_tipos
            WHERE nombre NOT ILIKE '%gira%'");
        $this->reancla('cat_tipos_evento');
        $this->command?->info(sprintf('  %-22s %d', 'cat_tipos_evento', CatTipoEvento::count()));

        // Combina fecha (YYYY-MM-DD) + hora (HH:MM) en timestamp, con guardas.
        $ts = fn (string $f, string $h) => "CASE WHEN {$f} ~ '^\\d{4}-\\d{2}-\\d{2}\$'
            THEN ({$f} || ' ' || CASE WHEN {$h} ~ '^\\d{1,2}:\\d{2}' THEN {$h} ELSE '00:00' END)::timestamp END";

        DB::statement("INSERT INTO tbl_eventos (
            id, titulo, descripcion, recomendaciones, inicio, fin, todo_el_dia, tipo_evento_id,
            lugar, contacto, telefono, personas, representante,
            asiste, confirmado, discurso, privado, deleted_at, created_at, updated_at)
            SELECT e.id, e.titulo, e.descripcion, e.recomendaciones,
                {$ts('e.fechainicio', 'e.horainicio')}, {$ts('e.fechatermino', 'e.horatermino')},
                COALESCE(e.completo, false), t.id,
                e.lugarevento, e.contacto, e.telefono, e.personas, e.representante,
                COALESCE(e.asiste,0) = 1, COALESCE(e.confirmado,0) = 1, COALESCE(e.discurso,0) = 1, COALESCE(e.evnt_prvd,0) = 1,
                {$del('e.')}, now(), now()
            FROM {$schema}.cal_eventos e
            LEFT JOIN cat_tipos_evento t ON t.id = NULLIF(e.tipo_id, 0)
            WHERE NOT EXISTS (
                SELECT 1 FROM {$schema}.cal_tipos g
                WHERE g.id = e.tipo_id AND g.nombre ILIKE '%gira%'
            )");
        $this->reancla('tbl_eventos');
        $this->command?->info(sprintf('  %-22s %d', 'tbl_eventos', Evento::count()));
    }

    private function reancla(string $tabla): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), COALESCE((SELECT MAX(id) FROM {$tabla}), 1))");
    }
}
