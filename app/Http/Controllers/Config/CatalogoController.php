<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Config\UpsertCatalogoRequest;
use App\Support\CatalogoRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD genérico de los catálogos simples (una pestaña por catálogo en la UI).
 * Toda la metadata (campos, modelos, opciones) proviene de CatalogoRegistry.
 */
class CatalogoController extends Controller
{
    public function index(): Response
    {
        $catalogos = collect(CatalogoRegistry::all())
            ->map(function (array $def, string $clave): array {
                /** @var class-string<Model> $model */
                $model = $def['model'];
                $columnas = array_map(fn (array $c): string => $c['name'], $def['campos']);

                // Opciones para los campos tipo select (catálogos relacionados).
                $opciones = [];
                foreach ($def['campos'] as $campo) {
                    if (($campo['type'] ?? 'text') === 'select') {
                        $opciones[$campo['name']] = $campo['options']::query()
                            ->orderBy('nombre')->get(['id', 'nombre']);
                    }
                }

                return [
                    'clave' => $clave,
                    'label' => $def['label'],
                    'campos' => array_map(fn (array $c): array => [
                        'name' => $c['name'],
                        'label' => $c['label'],
                        'type' => $c['type'] ?? 'text',
                        'required' => (bool) ($c['required'] ?? false),
                    ], $def['campos']),
                    'items' => $model::query()->orderBy('nombre')->get(array_merge(['id'], $columnas)),
                    'opciones' => $opciones,
                ];
            })
            ->values();

        return Inertia::render('config/catalogos/index', [
            'catalogos' => $catalogos,
        ]);
    }

    public function store(UpsertCatalogoRequest $request, string $catalogo): RedirectResponse
    {
        $def = $request->catalogo();
        $data = $request->validated();

        /** @var class-string<Model> $model */
        $model = $def['model'];
        $registro = isset($data['id'])
            ? $model::query()->findOrFail((int) $data['id'])
            : new $model;

        unset($data['id']);
        $registro->fill($data)->save();

        return back()->with('success', $registro->wasRecentlyCreated
            ? 'Registro creado correctamente.'
            : 'Registro actualizado correctamente.');
    }

    public function destroy(string $catalogo, int $id): RedirectResponse
    {
        $def = CatalogoRegistry::find($catalogo);
        abort_if($def === null, 404);

        /** @var class-string<Model> $model */
        $model = $def['model'];
        $model::query()->findOrFail($id)->delete();

        return back()->with('success', 'Registro eliminado correctamente.');
    }
}
