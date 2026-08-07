<?php

namespace App\Http\Controllers\Notas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notas\UpsertNotaRequest;
use App\Models\Nota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotaController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $this->filtros($request);

        $notas = Nota::query()
            ->with([
                'evento:id,titulo,inicio,lugar',
                'pendientes' => fn ($q) => $q->orderBy('id'),
            ])
            ->whereDate('fecha', '>=', $f['desde'])
            ->whereDate('fecha', '<=', $f['hasta'])
            // Igual que el legacy: si el evento al que colgaba la nota se
            // borró, la nota deja de listarse (queda huérfana, no se muestra).
            ->where(fn ($q) => $q->whereNull('evento_id')->orWhereHas('evento'))
            // Como el legacy (`ORDER BY id ASC`): la más vieja arriba.
            ->orderBy('id')
            ->get()
            ->map(fn (Nota $n): array => [
                'id' => $n->id,
                'nota' => $n->nota,
                'fecha' => $n->fecha?->toDateString(),
                'evento_id' => $n->evento_id,
                'evento_titulo' => $n->evento?->titulo,
                'evento' => $n->evento === null ? null : [
                    'titulo' => $n->evento->titulo,
                    'fecha' => $n->evento->inicio?->format('Y-m-d'),
                    'hora' => $n->evento->inicio?->format('H:i'),
                    'lugar' => $n->evento->lugar,
                ],
                'pendientes' => $n->pendientes->map(fn ($p): array => [
                    'id' => $p->id,
                    'texto' => $p->texto,
                    'realizado' => $p->realizado,
                ])->values(),
            ]);

        return Inertia::render('notas/index', [
            'filtros' => $f,
            'notas' => $notas,
        ]);
    }

    /**
     * Rango de fechas de la pantalla. Sin rango explícito el legacy muestra
     * **ayer y hoy**, no todo el histórico; se conserva esa conducta.
     *
     * @return array{desde: string, hasta: string}
     */
    private function filtros(Request $request): array
    {
        $desde = $request->date('desde')?->toDateString();
        $hasta = $request->date('hasta')?->toDateString();

        if ($desde === null && $hasta === null) {
            return [
                'desde' => now()->subDay()->toDateString(),
                'hasta' => now()->toDateString(),
            ];
        }

        return [
            'desde' => $desde ?? now()->subDay()->toDateString(),
            'hasta' => $hasta ?? now()->toDateString(),
        ];
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
