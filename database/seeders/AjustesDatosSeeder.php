<?php

namespace Database\Seeders;

use App\Models\Verificacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de las brechas de datos detectadas en la auditoría:
 *  - `control_administrativo` (legacy `ctrl_admon`).
 *  - `representante_id` de proveedores (legacy `rep_legal` = id de beneficiario).
 *  - ETL de `tbl_verificaciones` (legacy `tbl_verificar`).
 */
class AjustesDatosSeeder extends Seeder
{
    public function run(): void
    {
        $schema = config('plataforma.legacy_schema', 'hgo_pachuca');

        // 1) control_administrativo (ctrl_admon 0=ciudadana, 1=control admin).
        DB::statement("UPDATE tbl_solicitudes s
            SET control_administrativo = (leg.ctrl_admon = 1)
            FROM {$schema}.solicitud leg
            WHERE leg.id = s.id");
        $this->command?->info('  control_administrativo backfill OK');

        // 2) representante_id del proveedor (rep_legal numérico = beneficiario).
        DB::statement("UPDATE tbl_proveedores p
            SET representante_id = b.id
            FROM {$schema}.proveedor leg
            JOIN tbl_beneficiarios b
                ON b.id = (CASE WHEN leg.rep_legal ~ '^[0-9]+\$' THEN leg.rep_legal::int ELSE NULL END)
            WHERE p.id = leg.id");
        $this->command?->info('  proveedor representante_id backfill OK');

        // 3) Verificaciones.
        if (Verificacion::query()->exists()) {
            $this->command?->warn('  verificaciones ya pobladas; se omite el ETL.');

            return;
        }

        DB::statement("INSERT INTO tbl_verificaciones
            (id, solicitud_id, fecha, atendido, satisfecho, comentario, deleted_at, created_at, updated_at)
            SELECT v.id, v.id_solicitud, v.fecha, v.atendido, NULLIF(v.satisfecho, 0), v.comentario,
                CASE WHEN v.borrado = 1 THEN now() ELSE NULL END,
                COALESCE(v.fecha_creacion, now()), now()
            FROM {$schema}.tbl_verificar v
            JOIN tbl_solicitudes s ON s.id = v.id_solicitud");
        DB::statement("SELECT setval(pg_get_serial_sequence('tbl_verificaciones', 'id'), COALESCE((SELECT MAX(id) FROM tbl_verificaciones), 1))");
        $this->command?->info(sprintf('  %-22s %d', 'tbl_verificaciones', Verificacion::count()));
    }
}
