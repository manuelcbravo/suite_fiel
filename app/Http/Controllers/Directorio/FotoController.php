<?php

namespace App\Http\Controllers\Directorio;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Detenido;
use App\Models\Organizacion;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Permiso que exige cada tipo. La ruta cuelga del Directorio, pero un
     * detenido es un expediente de Seguridad ciudadana y no puede leerse con
     * el permiso del Directorio.
     *
     * @var array<string, Permiso>
     */
    private const PERMISOS = [
        'detenidos' => Permiso::SeguridadGestionar,
    ];

    /** Formatos que se sirven como imagen. Se excluye SVG a propósito: es un
     *  documento XML que puede llevar scripts y se ejecutaría en el origen de
     *  la aplicación. */
    private const FORMATOS = [
        'image/png' => true,
        'image/jpeg' => true,
        'image/jpg' => true,
        'image/gif' => true,
        'image/webp' => true,
    ];

    public function show(Request $request, string $tipo, int $id): JsonResponse
    {
        $entidad = $this->entidad($request, $tipo, $id);

        return response()->json(['foto' => $entidad->foto]);
    }

    /**
     * La misma foto pero como imagen, para poder usarla en un `<img src>` (las
     * tarjetas del directorio) y que el navegador la cachee en vez de viajar
     * en el payload de cada listado.
     */
    public function imagen(Request $request, string $tipo, int $id): SymfonyResponse
    {
        $foto = $this->entidad($request, $tipo, $id)->foto;

        abort_if(! is_string($foto) || $foto === '', 404);

        if (! preg_match('#^data:(image/[a-z.+-]+);base64,(.+)$#is', $foto, $partes)) {
            abort(404);
        }

        $tipoMime = mb_strtolower($partes[1]);

        abort_unless(isset(self::FORMATOS[$tipoMime]), 404);

        $binario = base64_decode($partes[2], true);

        abort_if($binario === false, 404);

        // El tipo declarado en el dato no basta: se confirma contra el
        // contenido real antes de servirlo.
        $medidas = @getimagesizefromstring($binario);

        abort_if($medidas === false || ! isset(self::FORMATOS[$medidas['mime'] ?? '']), 404);

        return response($binario, 200, [
            'Content-Type' => $medidas['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Resuelve la entidad comprobando que quien pide tenga el permiso del
     * módulo al que pertenece, no sólo el del Directorio.
     */
    private function entidad(Request $request, string $tipo, int $id): Model
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        $permiso = self::PERMISOS[$tipo] ?? null;

        if ($permiso !== null) {
            abort_unless($request->user()?->can($permiso->value), 403);
        }

        /** @var class-string<Model> $model */
        $model = self::TIPOS[$tipo];

        return $model::query()->findOrFail($id);
    }
}
