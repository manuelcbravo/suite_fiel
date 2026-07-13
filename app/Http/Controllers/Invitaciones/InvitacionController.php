<?php

namespace App\Http\Controllers\Invitaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invitaciones\StoreCorreoRequest;
use App\Http\Requests\Invitaciones\UpsertInvitacionRequest;
use App\Models\CatTipoEvento;
use App\Models\Invitacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitacionController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $invitaciones = Invitacion::query()
            ->with(['tipoEvento:id,nombre', 'evento:id,titulo'])
            ->withCount('correos')
            ->when($busqueda !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('titulo', "%{$busqueda}%")
                ->orWhereLike('destinatario', "%{$busqueda}%")))
            ->latest('inicio')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('invitaciones/index', [
            'invitaciones' => $invitaciones->getCollection()->map($this->serializar(...))->values(),
            'paginacion' => [
                'total' => $invitaciones->total(),
                'currentPage' => $invitaciones->currentPage(),
                'lastPage' => $invitaciones->lastPage(),
                'prevUrl' => $invitaciones->previousPageUrl(),
                'nextUrl' => $invitaciones->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'opciones' => [
                'tipos' => CatTipoEvento::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function store(UpsertInvitacionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $invitacion = isset($data['id'])
            ? Invitacion::query()->findOrFail((int) $data['id'])
            : new Invitacion;

        unset($data['id']);

        if (! $invitacion->exists) {
            $invitacion->created_by = $request->user()?->id;
        }

        $invitacion->fill($data)->save();

        return back()->with('success', $invitacion->wasRecentlyCreated
            ? 'Invitación creada correctamente.'
            : 'Invitación actualizada correctamente.');
    }

    public function destroy(Invitacion $invitacion): RedirectResponse
    {
        $invitacion->delete();

        return back()->with('success', 'Invitación eliminada correctamente.');
    }

    /**
     * Registra (y da por enviada) una notificación por correo. El envío real
     * por SMTP queda pendiente de configuración de correo.
     */
    public function notificar(StoreCorreoRequest $request, Invitacion $invitacion): RedirectResponse
    {
        $invitacion->correos()->create([
            'correos' => $request->validated()['correos'],
            'mensaje' => $request->validated()['mensaje'] ?? null,
            'enviado_en' => now(),
        ]);

        return back()->with('success', 'Notificación registrada.');
    }

    /**
     * Log de correos de una invitación (para el detalle).
     */
    public function correos(Invitacion $invitacion): JsonResponse
    {
        $items = $invitacion->correos()
            ->latest('id')
            ->get()
            ->map(fn ($c): array => [
                'id' => $c->id,
                'correos' => $c->correos,
                'mensaje' => $c->mensaje,
                'enviado_en' => $c->enviado_en?->toDateTimeString(),
            ]);

        return response()->json($items);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Invitacion $i): array
    {
        return [
            'id' => $i->id,
            'titulo' => $i->titulo,
            'destinatario' => $i->destinatario,
            'inicio' => $i->inicio?->toIso8601String(),
            'fin' => $i->fin?->toIso8601String(),
            'todo_el_dia' => $i->todo_el_dia,
            'tipo_evento_id' => $i->tipo_evento_id,
            'tipo_nombre' => $i->tipoEvento?->nombre,
            'evento_id' => $i->evento_id,
            'evento_titulo' => $i->evento?->titulo,
            'lugar' => $i->lugar,
            'descripcion' => $i->descripcion,
            'recomendaciones' => $i->recomendaciones,
            'contacto' => $i->contacto,
            'telefono' => $i->telefono,
            'fecha_recepcion' => $i->fecha_recepcion?->toDateTimeString(),
            'confirmado' => $i->confirmado,
            'atendida' => $i->atendida,
            'comentario' => $i->comentario,
            'correos_count' => $i->correos_count,
        ];
    }
}
