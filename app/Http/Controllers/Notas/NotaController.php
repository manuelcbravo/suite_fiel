<?php

namespace App\Http\Controllers\Notas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notas\UpsertNotaRequest;
use App\Models\Nota;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotaController extends Controller
{
    public function index(): Response
    {
        $notas = Nota::query()
            ->with(['evento:id,titulo', 'pendientes' => fn ($q) => $q->orderBy('id')])
            ->latest('fecha')
            ->latest('id')
            ->get()
            ->map(fn (Nota $n): array => [
                'id' => $n->id,
                'nota' => $n->nota,
                'fecha' => $n->fecha?->toDateString(),
                'evento_id' => $n->evento_id,
                'evento_titulo' => $n->evento?->titulo,
                'pendientes' => $n->pendientes->map(fn ($p): array => [
                    'id' => $p->id,
                    'texto' => $p->texto,
                    'realizado' => $p->realizado,
                ])->values(),
            ]);

        return Inertia::render('notas/index', [
            'notas' => $notas,
        ]);
    }

    public function store(UpsertNotaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $nota = isset($data['id'])
            ? Nota::query()->findOrFail((int) $data['id'])
            : new Nota;

        unset($data['id']);

        if (! $nota->exists) {
            $nota->created_by = $request->user()?->id;
        }

        $nota->fill($data)->save();

        return back()->with('success', $nota->wasRecentlyCreated
            ? 'Nota creada correctamente.'
            : 'Nota actualizada correctamente.');
    }

    public function destroy(Nota $nota): RedirectResponse
    {
        $nota->delete();

        return back()->with('success', 'Nota eliminada correctamente.');
    }
}
