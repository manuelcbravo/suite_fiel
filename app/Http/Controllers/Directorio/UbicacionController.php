<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Models\CatLocalidad;
use App\Models\CatMunicipio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lookups geográficos dependientes (municipios por estado, localidades por
 * municipio) para poblar los selects sin volcar el catálogo completo.
 * Reutilizable por todos los módulos (directorio, agenda, solicitudes…).
 */
class UbicacionController extends Controller
{
    public function municipios(Request $request): JsonResponse
    {
        $estadoId = $request->integer('estado_id');

        $municipios = $estadoId > 0
            ? CatMunicipio::query()
                ->where('estado_id', $estadoId)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
            : [];

        return response()->json($municipios);
    }

    public function localidades(Request $request): JsonResponse
    {
        $municipioId = $request->integer('municipio_id');

        $localidades = $municipioId > 0
            ? CatLocalidad::query()
                ->where('municipio_id', $municipioId)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
            : [];

        return response()->json($localidades);
    }
}
