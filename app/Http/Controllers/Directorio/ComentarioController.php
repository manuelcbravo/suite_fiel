<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Comentario;
use App\Models\Organizacion;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Comentarios polimórficos de las entidades del directorio
 * (beneficiario / organización / proveedor).
 */
class ComentarioController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const TIPOS = [
        'beneficiarios' => Beneficiario::class,
        'organizaciones' => Organizacion::class,
        'proveedores' => Proveedor::class,
    ];

    public function index(string $tipo, int $id): JsonResponse
    {
        $entidad = $this->entidad($tipo, $id);

        $comentarios = $entidad->comentarios()
            ->with('creadoPor:id,name')
            ->latest('id')
            ->get()
            ->map(fn (Comentario $c): array => [
                'id' => $c->id,
                'comentario' => $c->comentario,
                'autor' => $c->creadoPor?->name,
                'fecha' => $c->created_at?->toDateTimeString(),
            ]);

        return response()->json($comentarios);
    }

    public function store(Request $request, string $tipo, int $id): RedirectResponse
    {
        $data = $request->validate([
            'comentario' => ['required', 'string', 'max:2000'],
        ]);

        $this->entidad($tipo, $id)->comentarios()->create([
            'comentario' => $data['comentario'],
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Comentario agregado.');
    }

    public function destroy(Comentario $comentario): RedirectResponse
    {
        $comentario->delete();

        return back()->with('success', 'Comentario eliminado.');
    }

    /**
     * Resuelve la entidad del directorio según el tipo de ruta.
     */
    private function entidad(string $tipo, int $id): Model
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        /** @var class-string<Model> $model */
        $model = self::TIPOS[$tipo];

        return $model::query()->findOrFail($id);
    }
}
