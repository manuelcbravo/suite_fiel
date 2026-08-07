<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo legacy `cal_tipos` traía dos filas por cada tipo de evento
 * (dos altas sucesivas), así que el panel de la agenda mostraba "Audiencia",
 * "Cumpleaños", "Efemérides"… repetidos.
 *
 * Se conserva la fila de id más bajo —la original—, los eventos que colgaban
 * de la repetida se pasan a ella y la repetida se marca como eliminada (queda
 * recuperable). Es idempotente: si ya no hay duplicados no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicados = DB::table('cat_tipos_evento')
            ->whereNull('deleted_at')
            ->select('nombre')
            ->groupBy('nombre')
            ->havingRaw('count(*) > 1')
            ->pluck('nombre');

        foreach ($duplicados as $nombre) {
            $ids = DB::table('cat_tipos_evento')
                ->whereNull('deleted_at')
                ->where('nombre', $nombre)
                ->orderBy('id')
                ->pluck('id');

            $conserva = $ids->shift();

            if ($conserva === null || $ids->isEmpty()) {
                continue;
            }

            DB::table('tbl_eventos')
                ->whereIn('tipo_evento_id', $ids)
                ->update(['tipo_evento_id' => $conserva]);

            DB::table('cat_tipos_evento')
                ->whereIn('id', $ids)
                ->update(['deleted_at' => now()]);
        }
    }

    public function down(): void
    {
        // No se revierte: no queda registro de qué evento tenía cuál de las dos
        // filas repetidas, y ambas significaban lo mismo.
    }
};
