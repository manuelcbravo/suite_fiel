<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `folio_sistema` es un consecutivo, pero la columna es `varchar(300)` porque
 * así venía del legacy. Ordenar el expediente por ella lo hacía alfabéticamente
 * y la solicitud 9 salía por encima de la 10.
 *
 * Se añade el mismo folio como entero para poder ordenar (e indexar) de verdad.
 * El modelo lo mantiene al día en cada guardado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_solicitudes', function (Blueprint $table): void {
            $table->unsignedBigInteger('folio_num')->nullable()->after('folio_sistema');
            $table->index('folio_num');
        });

        // Relleno inicial: los dígitos del folio de sistema y, si no trae
        // ninguno, el id (que es lo que `store()` usa al crear).
        DB::table('tbl_solicitudes')
            ->select('id', 'folio_sistema')
            ->orderBy('id')
            ->chunkById(1000, function ($filas): void {
                foreach ($filas as $fila) {
                    $digitos = preg_replace('/\D+/', '', (string) $fila->folio_sistema);

                    DB::table('tbl_solicitudes')
                        ->where('id', $fila->id)
                        ->update(['folio_num' => $digitos === '' || $digitos === null
                            ? $fila->id
                            : (int) $digitos]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tbl_solicitudes', function (Blueprint $table): void {
            $table->dropIndex(['folio_num']);
            $table->dropColumn('folio_num');
        });
    }
};
