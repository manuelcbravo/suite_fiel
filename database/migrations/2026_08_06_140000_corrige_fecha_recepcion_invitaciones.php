<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La tabla legacy `invitaciones` tiene **dos** fechas parecidas:
 *
 *  - `fecha_recepcion` (timestamp) — cuándo se capturó el registro en la
 *    plataforma, al segundo. Es un `created_at` disfrazado.
 *  - `fecharecp` (varchar) — la **fecha de recepción que teclea el usuario**
 *    en el formulario (`nvtcn_add.php`, campo "Fecha de recepción").
 *
 * El ETL tomó la primera por su nombre, así que la fecha que de verdad captura
 * el usuario se perdió (32 de 90 la tienen) y todos los `created_at` quedaron
 * con la fecha del ETL. Aquí se corrigen las dos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        if (! $this->hayLegacy($schema)) {
            return;
        }

        DB::statement("UPDATE tbl_invitaciones i
            SET fecha_recepcion = CASE
                    WHEN l.fecharecp ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}'
                    THEN left(l.fecharecp, 10)::timestamp
                END,
                created_at = COALESCE(l.fecha_recepcion, i.created_at)
            FROM {$schema}.invitaciones l
            WHERE l.id = i.id");
    }

    public function down(): void
    {
        // Sin vuelta atrás: la corrección recupera un dato que se había
        // perdido y revertirla lo volvería a borrar.
    }

    private function hayLegacy(string $schema): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne(
            'SELECT 1 AS existe FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
            [$schema, 'invitaciones'],
        ) !== null;
    }
};
