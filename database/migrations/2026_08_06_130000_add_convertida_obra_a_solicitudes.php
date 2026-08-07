<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `solicitud.obra` del legacy: en qué punto va el paso de solicitud a obra.
 *
 *   0 = no es obra
 *   1 = **convertida** a obra (`solicitudes/convertir.php`, que además pone
 *       la solicitud en estatus 5) pero todavía sin expediente
 *   2 = **importada** al módulo de obra pública (`obrapublica/importar_obra.php`)
 *
 * Quedó fuera cuando Obra pública estaba excluida, pero el reporte de Gestión
 * filtra por ella (el histórico excluye 1 y 2), así que se recupera. En el
 * demo son 231 convertidas y 121 importadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_solicitudes', function (Blueprint $t): void {
            $t->unsignedTinyInteger('convertida_obra')->default(0)->after('control_administrativo');
        });

        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if ($this->hayLegacy($schema)) {
            DB::statement("UPDATE tbl_solicitudes s
                SET convertida_obra = COALESCE(l.obra, 0)
                FROM {$schema}.solicitud l
                WHERE l.id = s.id AND l.obra IN (1, 2)");
        }
    }

    public function down(): void
    {
        Schema::table('tbl_solicitudes', function (Blueprint $t): void {
            $t->dropColumn('convertida_obra');
        });
    }

    private function hayLegacy(string $schema): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne(
            'SELECT 1 AS existe FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$schema, 'solicitud'],
        ) !== null;
    }
};
