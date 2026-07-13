<?php

namespace Database\Seeders;

use App\Models\Invitacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ETL de Invitaciones (Módulo 6) desde el schema legacy.
 *
 * Consolida fecha+hora (texto) en timestamps; vincula al tipo de evento y,
 * si existe, al evento migrado de la agenda. Migra el log de correos.
 */
class InvitacionesSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if (Invitacion::query()->exists()) {
            $this->command?->warn('Invitaciones ya pobladas; se omite el ETL.');

            return;
        }

        $del = fn (string $a = '') => "CASE WHEN {$a}borrado = 1 THEN now() ELSE NULL END";
        $ts = fn (string $f, string $h) => "CASE WHEN {$f} ~ '^\\d{4}-\\d{2}-\\d{2}\$'
            THEN ({$f} || ' ' || CASE WHEN {$h} ~ '^\\d{1,2}:\\d{2}' THEN {$h} ELSE '00:00' END)::timestamp END";

        DB::statement("INSERT INTO tbl_invitaciones (
            id, titulo, destinatario, inicio, fin, todo_el_dia, tipo_evento_id, evento_id,
            lugar, descripcion, recomendaciones, contacto, telefono,
            fecha_recepcion, confirmado, atendida, comentario, deleted_at, created_at, updated_at)
            SELECT i.id, i.titulo, i.destinatario,
                {$ts('i.fechainicio', 'i.horainicio')}, {$ts('i.fechatermino', 'i.horatermino')},
                COALESCE(i.completo, false), t.id, e.id,
                i.lugarevento, i.descripcion, i.recomendaciones, i.contacto, i.telefono,
                i.fecha_recepcion, COALESCE(i.confirmado,0) = 1, COALESCE(i.atendida,0) = 1, i.comentario,
                {$del('i.')}, now(), now()
            FROM {$schema}.invitaciones i
            LEFT JOIN cat_tipos_evento t ON t.id = NULLIF(i.tipo, 0)
            LEFT JOIN tbl_eventos e ON e.id = NULLIF(i.id_evento, 0)");
        $this->reancla('tbl_invitaciones');
        $this->command?->info(sprintf('  %-24s %d', 'tbl_invitaciones', Invitacion::count()));

        DB::statement("INSERT INTO tbl_invitacion_correos (
            id, invitacion_id, correos, mensaje, enviado_en, deleted_at, created_at, updated_at)
            SELECT c.id, c.invitacion_id, c.correos, c.mensaje, c.fecha, {$del('c.')}, now(), now()
            FROM {$schema}.invitaciones_correo c
            JOIN tbl_invitaciones inv ON inv.id = c.invitacion_id");
        $this->reancla('tbl_invitacion_correos');
        $this->command?->info(sprintf('  %-24s %d', 'tbl_invitacion_correos', DB::table('tbl_invitacion_correos')->count()));
    }

    private function reancla(string $tabla): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), COALESCE((SELECT MAX(id) FROM {$tabla}), 1))");
    }
}
