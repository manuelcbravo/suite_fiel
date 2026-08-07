<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `obra_estimaciones.doc_obligatoria` es la lista de papeles que acompañan a
 * la estimación, guardada como ids separados por coma ("1,2,3,4,5,6,7,8,9"),
 * igual que `invitacion_acto`. Trae dato en **las 207** estimaciones y el ETL
 * no la había traído.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_obra_estimaciones', function (Blueprint $t): void {
            $t->string('doc_obligatoria')->nullable()->after('avance_financiero');
        });

        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if ($this->hayLegacy($schema)) {
            DB::statement("UPDATE tbl_obra_estimaciones e
                SET doc_obligatoria = NULLIF(o.doc_obligatoria, '')
                FROM {$schema}.obra_estimaciones o
                WHERE o.id = e.id");
        }
    }

    public function down(): void
    {
        Schema::table('tbl_obra_estimaciones', function (Blueprint $t): void {
            $t->dropColumn('doc_obligatoria');
        });
    }

    private function hayLegacy(string $schema): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne(
            'SELECT 1 AS existe FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$schema, 'obra_estimaciones'],
        ) !== null;
    }
};
