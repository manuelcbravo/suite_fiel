<?php

namespace Database\Seeders;

use App\Models\Nota;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ETL de Notas (Módulo 5) desde el schema legacy.
 */
class NotasSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if (Nota::query()->exists()) {
            $this->command?->warn('Notas ya pobladas; se omite el ETL.');

            return;
        }

        $del = fn (string $a = '') => "CASE WHEN {$a}borrado = 1 THEN now() ELSE NULL END";

        DB::statement("INSERT INTO tbl_notas (id, nota, fecha, evento_id, deleted_at, created_at, updated_at)
            SELECT n.id, n.nota, n.fecha, e.id, {$del('n.')}, COALESCE(n.fecha::timestamp, now()), now()
            FROM {$schema}.notas n
            LEFT JOIN tbl_eventos e ON e.id = NULLIF(n.evento_id, 0)");
        $this->reancla('tbl_notas');
        $this->command?->info(sprintf('  %-18s %d', 'tbl_notas', Nota::count()));

        DB::statement("INSERT INTO tbl_notitas (id, nota_id, texto, realizado, deleted_at, created_at, updated_at)
            SELECT t.id, t.nota_id, t.texto, COALESCE(t.realizado,0) = 1, {$del('t.')}, COALESCE(t.fecha, now()), now()
            FROM {$schema}.notitas t
            JOIN tbl_notas n ON n.id = t.nota_id");
        $this->reancla('tbl_notitas');
        $this->command?->info(sprintf('  %-18s %d', 'tbl_notitas', DB::table('tbl_notitas')->count()));
    }

    private function reancla(string $tabla): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), COALESCE((SELECT MAX(id) FROM {$tabla}), 1))");
    }
}
