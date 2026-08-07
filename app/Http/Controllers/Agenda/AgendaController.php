<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\UpsertEventoRequest;
use App\Models\CatEstado;
use App\Models\CatTipoEvento;
use App\Models\Evento;
use App\Models\Nota;
use App\Models\Notita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('agenda/index', [
            'tipos' => CatTipoEvento::query()->orderBy('nombre')->get(['id', 'nombre', 'color']),
            'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
            'opcionesAsiste' => collect(Evento::ASISTE)
                ->map(fn (string $n, int $id) => ['id' => $id, 'nombre' => $n])
                ->values(),
        ]);
    }

    /**
     * Feed de eventos para FullCalendar (rango start/end en ISO).
     */
    public function eventos(Request $request): JsonResponse
    {
        $inicio = $request->string('start')->toString();
        $fin = $request->string('end')->toString();

        $eventos = Evento::query()
            ->with(['tipoEvento:id,nombre,color', 'localidad:id,nombre,municipio_id,estado_id'])
            ->when($inicio !== '' && $fin !== '', fn ($q) => $q
                ->where('inicio', '<', $fin)
                ->where(fn ($sub) => $sub->where('fin', '>=', $inicio)->orWhereNull('fin')->where('inicio', '>=', $inicio)))
            ->whereNotNull('inicio')
            ->get()
            ->map(fn (Evento $e): array => [
                'id' => (string) $e->id,
                'title' => $e->titulo ?: '(Sin título)',
                // Sin zona horaria a propósito: las horas del evento son de
                // reloj de pared (como se capturaron), no instantes UTC. Si se
                // mandaran con offset el navegador las correría varias horas.
                'start' => $e->inicio?->format('Y-m-d\TH:i:s'),
                'end' => $e->fin?->format('Y-m-d\TH:i:s'),
                'allDay' => $e->todo_el_dia,
                'backgroundColor' => $e->tipoEvento?->color,
                'borderColor' => $e->tipoEvento?->color,
                'extendedProps' => [
                    'tipo_evento_id' => $e->tipo_evento_id,
                    'tipo_nombre' => $e->tipoEvento?->nombre,
                    'descripcion' => $e->descripcion,
                    'lugar' => $e->lugar,
                    'localidad_id' => $e->localidad_id,
                    // El selector de localidad es en cascada; se mandan el
                    // estado y el municipio para poder precargarlo al editar.
                    'estado_id' => $e->localidad?->estado_id,
                    'municipio_id' => $e->localidad?->municipio_id,
                    'localidad_nombre' => $e->localidad?->nombre,
                    'contacto' => $e->contacto,
                    'telefono' => $e->telefono,
                    'representante' => $e->representante,
                    'personas' => $e->personas,
                    'recomendaciones' => $e->recomendaciones,
                    'asiste' => $e->asiste,
                    'confirmado' => $e->confirmado,
                    'discurso' => $e->discurso,
                    'privado' => $e->privado,
                ],
            ]);

        return response()->json($eventos);
    }

    /**
     * Búsqueda ligera de eventos (para vincular invitaciones, etc.).
     */
    public function buscar(Request $request): JsonResponse
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $eventos = Evento::query()
            ->when($busqueda !== '', fn ($q) => $q->whereLike('titulo', "%{$busqueda}%"))
            ->whereNotNull('inicio')
            ->orderByDesc('inicio')
            ->limit(20)
            ->get(['id', 'titulo', 'inicio'])
            ->map(fn (Evento $e): array => [
                'id' => $e->id,
                'nombre' => trim(($e->titulo ?: '(Sin título)').' — '.$e->inicio?->format('d/m/Y H:i')),
            ]);

        return response()->json($eventos);
    }

    public function store(UpsertEventoRequest $request): RedirectResponse
    {
        $evento = new Evento;
        $evento->created_by = $request->user()?->id;
        $evento->fill($request->validated())->save();

        return back()->with('success', 'Evento creado correctamente.');
    }

    public function update(UpsertEventoRequest $request, Evento $evento): RedirectResponse
    {
        $evento->fill($request->validated())->save();

        return back()->with('success', 'Evento actualizado correctamente.');
    }

    public function destroy(Evento $evento): RedirectResponse
    {
        $evento->delete();

        return back()->with('success', 'Evento eliminado correctamente.');
    }

    /**
     * Notas (checklist) del evento. Cada evento tiene una nota única auto-creada
     * y titulada con su nombre (replica el flujo legacy calendario_notas).
     */
    public function notas(Request $request, Evento $evento): JsonResponse
    {
        $nota = $this->notaDelEvento($request, $evento);

        return response()->json([
            'nota_id' => $nota->id,
            'titulo' => $nota->nota,
            'pendientes' => $nota->pendientes()
                ->latest('id')
                ->get(['id', 'texto', 'realizado'])
                ->map(fn (Notita $n): array => [
                    'id' => $n->id,
                    'texto' => $n->texto,
                    'realizado' => $n->realizado,
                ]),
        ]);
    }

    /**
     * Agrega un pendiente a la nota del evento (creándola si no existe).
     */
    public function agregarNota(Request $request, Evento $evento): RedirectResponse
    {
        $data = $request->validate([
            'texto' => ['required', 'string', 'max:255'],
        ]);

        $nota = $this->notaDelEvento($request, $evento);
        $nota->pendientes()->create([
            'texto' => $data['texto'],
            'realizado' => false,
        ]);

        return back()->with('success', 'Nota del evento agregada.');
    }

    /**
     * Resuelve (o crea) la nota única asociada al evento y sincroniza su título.
     */
    private function notaDelEvento(Request $request, Evento $evento): Nota
    {
        $titulo = 'NOTAS DEL EVENTO: '.($evento->titulo ?: '(Sin título)');

        $nota = Nota::query()->firstOrNew(['evento_id' => $evento->id]);
        if (! $nota->exists) {
            $nota->fecha = now()->toDateString();
            $nota->created_by = $request->user()?->id;
        }
        if ($nota->nota !== $titulo) {
            $nota->nota = $titulo;
        }
        $nota->save();

        return $nota;
    }
}
