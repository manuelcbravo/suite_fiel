<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Organizacion;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Devuelve la foto (data URI) de una entidad del directorio bajo demanda,
 * para no cargarla en el listado (las fotos legacy pesan hasta ~90 KB).
 */
class FotoController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const TIPOS = [
        'beneficiarios' => Beneficiario::class,
        'organizaciones' => Organizacion::class,
        'proveedores' => Proveedor::class,
    ];

    public function show(string $tipo, int $id): JsonResponse
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        /** @var class-string<Model> $model */
        $model = self::TIPOS[$tipo];
        $entidad = $model::query()->findOrFail($id);

        return response()->json(['foto' => $entidad->foto]);
    }
}
