<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tres claves del legacy no son acuses de "sí / no" y no cabían en
 * `tbl_obra_documentos`:
 *
 *  - `invitacion_acto` guarda **quiénes fueron invitados** al acto de entrega
 *    como una lista de ids separados por coma ("1,2,3,4,5"). El ETL la leyó
 *    como si fuera sí/no y dejó 57 renglones sin valor; aquí se rescata a una
 *    columna propia del cierre y se rehidrata desde el legacy.
 *  - `oficio_invitacion_contrato` y `acta_constitutiva` no se dibujan como
 *    acuse en ninguna pantalla del legacy y no traen datos: se retiran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_obra_cierre', function (Blueprint $t): void {
            $t->string('invitados_acto')->nullable()->after('fecha_invitacion_acto');
        });

        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if ($this->hayLegacy($schema)) {
            DB::statement("UPDATE tbl_obra_cierre c
                SET invitados_acto = NULLIF(o.invitacion_acto, '')
                FROM {$schema}.obra o
                WHERE o.id = c.obra_id");
        }

        DB::table('tbl_obra_documentos')
            ->whereIn('clave', ['invitacion_acto', 'oficio_invitacion_contrato', 'acta_constitutiva'])
            ->delete();
    }

    public function down(): void
    {
        Schema::table('tbl_obra_cierre', function (Blueprint $t): void {
            $t->dropColumn('invitados_acto');
        });
    }

    /** En pruebas no existe el schema legacy y no hay nada que rehidratar. */
    private function hayLegacy(string $schema): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne(
            'SELECT 1 AS existe FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$schema, 'obra'],
        ) !== null;
    }
};
