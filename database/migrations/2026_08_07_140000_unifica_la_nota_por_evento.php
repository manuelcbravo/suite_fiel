<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada evento de la agenda tiene UNA nota (checklist). Nada lo garantizaba:
 * `notaDelEvento()` hacía `firstOrNew` + `save()`, así que dos peticiones a la
 * vez creaban dos notas del mismo evento y los pendientes quedaban repartidos
 * entre ambas.
 *
 * Se consolidan los duplicados que hubiera y se añade el índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->consolidarDuplicados();

        Schema::table('tbl_notas', function (Blueprint $table): void {
            $table->unique('evento_id');
        });
    }

    /** Deja la nota más antigua de cada evento y le pasa los pendientes. */
    private function consolidarDuplicados(): void
    {
        $eventos = DB::table('tbl_notas')
            ->whereNotNull('evento_id')
            ->select('evento_id')
            ->groupBy('evento_id')
            ->havingRaw('count(*) > 1')
            ->pluck('evento_id');

        foreach ($eventos as $eventoId) {
            $notas = DB::table('tbl_notas')
                ->where('evento_id', $eventoId)
                ->orderBy('id')
                ->pluck('id');

            $superviviente = $notas->shift();

            DB::table('tbl_notitas')->whereIn('nota_id', $notas)->update(['nota_id' => $superviviente]);
            DB::table('tbl_notas')->whereIn('id', $notas)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('tbl_notas', function (Blueprint $table): void {
            $table->dropUnique(['evento_id']);
        });
    }
};
