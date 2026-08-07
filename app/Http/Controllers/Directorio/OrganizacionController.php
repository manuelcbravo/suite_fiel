<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Directorio\UpsertOrganizacionRequest;
use App\Models\Organizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizacionController extends Controller
{
    public function store(UpsertOrganizacionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $organizacion = isset($data['id'])
            ? Organizacion::query()->findOrFail((int) $data['id'])
            : new Organizacion;

        unset($data['id']);

        if (! $organizacion->exists) {
            $organizacion->created_by = $request->user()?->id;
        }

        $organizacion->fill($data)->save();

        return back()->with('success', $organizacion->wasRecentlyCreated
            ? 'Organización creada correctamente.'
            : 'Organización actualizada correctamente.');
    }

    public function destroy(Organizacion $organizacion): RedirectResponse
    {
        $organizacion->delete();

        return back()->with('success', 'Organización eliminada correctamente.');
    }

    /**
     * Búsqueda ligera para selectores (p. ej. solicitante en Gestión).
     */
    public function buscar(Request $request): JsonResponse
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $resultados = Organizacion::query()
            ->when($busqueda !== '', fn (Builder $q) => $q->whereLike('nombre', "%{$busqueda}%"))
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'nombre'])
            ->map(fn (Organizacion $o): array => [
                'id' => $o->id,
                'nombre' => $o->nombre,
            ]);

        return response()->json($resultados);
    }
}
