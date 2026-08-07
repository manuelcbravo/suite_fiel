<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Detenido;
use App\Models\Organizacion;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
        // Los detenidos también guardan foto; comparten el mismo servicio.
        'detenidos' => Detenido::class,
    ];

    public function show(string $tipo, int $id): JsonResponse
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        /** @var class-string<Model> $model */
        $model = self::TIPOS[$tipo];
        $entidad = $model::query()->findOrFail($id);

        return response()->json(['foto' => $entidad->foto]);
    }

    /**
     * La misma foto pero como imagen, para poder usarla en un `<img src>` (las
     * tarjetas del directorio) y que el navegador la cachee en vez de viajar
     * en el payload de cada listado.
     */
    public function imagen(string $tipo, int $id): SymfonyResponse
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        /** @var class-string<Model> $model */
        $model = self::TIPOS[$tipo];
        $foto = $model::query()->whereKey($id)->value('foto');

        abort_if(! is_string($foto) || $foto === '', 404);

        if (! preg_match('#^data:(image/[a-z.+-]+);base64,(.+)$#is', $foto, $partes)) {
            abort(404);
        }

        $binario = base64_decode($partes[2], true);

        abort_if($binario === false, 404);

        return response($binario, 200, [
            'Content-Type' => $partes[1],
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
