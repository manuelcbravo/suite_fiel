<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\AtenderSolicitudRequest;
use App\Http\Requests\Gestion\UpsertSolicitudRequest;
use App\Models\Beneficiario;
use App\Models\CatArea;
use App\Models\CatConcepto;
use App\Models\CatDependencia;
use App\Models\CatEstado;
use App\Models\CatOrigenRecurso;
use App\Models\CatOrigenSolicitud;
use App\Models\CatRubro;
use App\Models\CatSector;
use App\Models\CatUnidadMedida;
use App\Models\Organizacion;
use App\Models\Solicitud;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $solicitudes = Solicitud::query()
            ->with(['solicitante', 'concepto:id,nombre', 'municipioResp:id,nombre', 'rubros:id', 'sectores:id'])
            ->withCount('seguimientos')
            ->when($busqueda !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('folio', "%{$busqueda}%")
                ->orWhereLike('folio_sistema', "%{$busqueda}%")
                ->orWhereLike('solicitud', "%{$busqueda}%")))
            ->latest('fecha_recepcion')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('gestion/solicitudes/index', [
            'solicitudes' => $solicitudes->getCollection()->map($this->serializar(...))->values(),
            'paginacion' => [
                'total' => $solicitudes->total(),
                'currentPage' => $solicitudes->currentPage(),
                'lastPage' => $solicitudes->lastPage(),
                'prevUrl' => $solicitudes->previousPageUrl(),
                'nextUrl' => $solicitudes->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'opciones' => [
                'estatus' => collect(Solicitud::ESTATUS)->map(fn (string $n, int $id) => ['id' => $id, 'nombre' => $n])->values(),
                'conceptos' => CatConcepto::query()->orderBy('nombre')->get(['id', 'nombre']),
                'procedencias' => CatOrigenSolicitud::query()->orderBy('nombre')->get(['id', 'nombre']),
                'rubros' => CatRubro::query()->orderBy('nombre')->get(['id', 'nombre']),
                'sectores' => CatSector::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
                'dependencias' => CatDependencia::query()->orderBy('nombre')->get(['id', 'nombre']),
                'areas' => CatArea::query()->orderBy('nombre')->get(['id', 'nombre', 'dependencia_id']),
                'unidadesMedida' => CatUnidadMedida::query()->orderBy('nombre')->get(['id', 'nombre']),
                'origenesRecurso' => CatOrigenRecurso::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    /**
     * Registra la atención/resolución de una solicitud turnada, capturando el
     * apoyo entregado y moviendo el estatus (replica el flujo legacy
     * seg_turnar/seg_resolver: temporal → rápida → atendida).
     */
    public function atender(AtenderSolicitudRequest $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validated();
        $decision = $data['decision'];

        // Apoyo capturado en la solicitud (solo los campos presentes).
        $mapa = [
            'apoyo' => 'apoyo',
            'cantidad' => 'cantidad',
            'unidad_medida_id' => 'tipo',
            'monto' => 'monto',
            'num_bene' => 'num_bene',
            'concepto_id' => 'concepto_id',
            'origen_recurso_id' => 'origen',
            'estado_resp_id' => 'estado_resp_id',
            'municipio_resp_id' => 'municipio_resp_id',
            'localidad_resp_id' => 'localidad_resp_id',
            'folio_resp' => 'folio_resp',
            'fecha_resp' => 'fecha_resp',
        ];
        foreach ($mapa as $entrada => $columna) {
            if (array_key_exists($entrada, $data)) {
                $solicitud->{$columna} = $data[$entrada];
            }
        }

        // Estatus según la decisión.
        $estatusResp = match ($decision) {
            'resolver' => 5,
            'rapida' => 6,
            default => 4, // respuesta temporal
        };
        if ($decision === 'resolver') {
            $solicitud->status = 5;
        } elseif ($decision === 'rapida') {
            $solicitud->status = 6;
        }
        $solicitud->save();

        if (array_key_exists('rubros', $data)) {
            $solicitud->rubros()->sync($data['rubros']);
        }
        if (array_key_exists('sectores', $data)) {
            $solicitud->sectores()->sync($data['sectores']);
        }

        // Refleja la respuesta en el último seguimiento (turnado).
        $seguimiento = $solicitud->seguimientos()->latest('id')->first();
        if ($seguimiento) {
            $seguimiento->update([
                'respuesta' => $data['respuesta'] ?? $seguimiento->respuesta,
                'estatus_resp' => $estatusResp,
                'avance' => $data['avance'] ?? $seguimiento->avance,
                'fecha_respuesta' => now(),
                'estatus' => $decision === 'temporal' ? $seguimiento->estatus : 3,
            ]);
        }

        return back()->with('success', match ($decision) {
            'resolver' => 'Solicitud atendida.',
            'rapida' => 'Atención rápida registrada.',
            default => 'Respuesta parcial registrada.',
        });
    }

    public function store(UpsertSolicitudRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $rubros = $data['rubros'] ?? [];
        $sectores = $data['sectores'] ?? [];
        unset($data['rubros'], $data['sectores']);

        // Solicitante polimórfico: beneficiario u organización.
        $tipo = $data['solicitante_tipo'] ?? ($data['beneficiario_id'] ?? null ? 'beneficiario' : null);
        if ($tipo === 'organizacion' && ! empty($data['organizacion_id'])) {
            $data['solicitante_type'] = Organizacion::class;
            $data['solicitante_id'] = $data['organizacion_id'];
        } elseif ($tipo === 'beneficiario' && ! empty($data['beneficiario_id'])) {
            $data['solicitante_type'] = Beneficiario::class;
            $data['solicitante_id'] = $data['beneficiario_id'];
        }
        unset($data['solicitante_tipo'], $data['beneficiario_id'], $data['organizacion_id']);

        $solicitud = isset($data['id'])
            ? Solicitud::query()->findOrFail((int) $data['id'])
            : new Solicitud;

        unset($data['id']);

        if (! $solicitud->exists) {
            $solicitud->created_by = $request->user()?->id;
        }

        $solicitud->fill($data)->save();
        $solicitud->rubros()->sync($rubros);
        $solicitud->sectores()->sync($sectores);

        return back()->with('success', $solicitud->wasRecentlyCreated
            ? 'Solicitud creada correctamente.'
            : 'Solicitud actualizada correctamente.');
    }

    public function destroy(Solicitud $solicitud): RedirectResponse
    {
        $solicitud->delete();

        return back()->with('success', 'Solicitud eliminada correctamente.');
    }

    /**
     * Seguimientos (turnado/respuesta) de una solicitud, para el detalle.
     */
    public function seguimientos(Solicitud $solicitud): \Illuminate\Http\JsonResponse
    {
        $items = $solicitud->seguimientos()
            ->with(['dependencia:id,nombre', 'area:id,nombre'])
            ->latest('id')
            ->get()
            ->map(fn ($sg): array => [
                'id' => $sg->id,
                'estatus' => $sg->estatus,
                'estatus_label' => $sg->estatusLabel(),
                'dependencia_id' => $sg->dependencia_id,
                'dependencia' => $sg->dependencia?->nombre,
                'area_id' => $sg->area_id,
                'area' => $sg->area?->nombre,
                'instruccion' => $sg->instruccion,
                'comentario' => $sg->comentario,
                'respuesta' => $sg->respuesta,
                'responsable' => $sg->responsable,
                'avance' => $sg->avance,
                'fecha' => $sg->created_at?->toDateTimeString(),
                'fecha_respuesta' => $sg->fecha_respuesta?->toDateTimeString(),
            ]);

        return response()->json($items);
    }

    /**
     * Verificaciones (satisfacción) de una solicitud atendida.
     */
    public function verificaciones(Solicitud $solicitud): \Illuminate\Http\JsonResponse
    {
        $items = $solicitud->verificaciones()
            ->with('creadoPor:id,name')
            ->latest('id')
            ->get()
            ->map(fn (\App\Models\Verificacion $v): array => [
                'id' => $v->id,
                'fecha' => $v->fecha?->toDateString(),
                'atendido' => $v->atendido,
                'satisfecho_label' => \App\Models\Verificacion::SATISFACCION[$v->satisfecho] ?? null,
                'comentario' => $v->comentario,
                'autor' => $v->creadoPor?->name,
            ]);

        return response()->json($items);
    }

    public function registrarVerificacion(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'comentario' => ['required', 'string', 'max:2000'],
            'atendido' => ['nullable', 'integer', 'in:0,1'],
            'satisfecho' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $solicitud->verificaciones()->create([
            'fecha' => now()->toDateString(),
            'atendido' => $data['atendido'] ?? null,
            'satisfecho' => $data['satisfecho'] ?? null,
            'comentario' => $data['comentario'],
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Verificación registrada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Solicitud $s): array
    {
        return [
            'id' => $s->id,
            'folio' => $s->folio,
            'folio_sistema' => $s->folio_sistema,
            'solicitud' => $s->solicitud,
            'apoyo' => $s->apoyo,
            'cantidad' => $s->cantidad,
            'monto' => $s->monto,
            'desc_bene' => $s->desc_bene,
            'num_bene' => $s->num_bene,
            'status' => $s->status,
            'status_label' => $s->estatusLabel(),
            'control_administrativo' => $s->control_administrativo,
            'prioridad' => $s->prioridad,
            'fecha_recepcion' => $s->fecha_recepcion?->toDateString(),
            'concepto_id' => $s->concepto_id,
            'procedencia_id' => $s->procedencia_id,
            'origen' => $s->origen,
            'unidad_medida_id' => $s->tipo,
            'origen_recurso_id' => is_numeric($s->origen) ? (int) $s->origen : null,
            'folio_resp' => $s->folio_resp,
            'fecha_resp' => $s->fecha_resp,
            'solicitante_tipo' => $s->solicitante ? class_basename($s->solicitante) : null,
            'solicitante_id' => $s->solicitante_id,
            'solicitante_nombre' => $this->nombreSolicitante($s),
            'municipio_resp_nombre' => $s->municipioResp?->nombre,
            'estado_resp_id' => $s->estado_resp_id,
            'municipio_resp_id' => $s->municipio_resp_id,
            'localidad_resp_id' => $s->localidad_resp_id,
            'rubros' => $s->rubros->pluck('id'),
            'sectores' => $s->sectores->pluck('id'),
            'seguimientos_count' => $s->seguimientos_count,
        ];
    }

    private function nombreSolicitante(Solicitud $s): ?string
    {
        $ente = $s->solicitante;

        if ($ente instanceof Beneficiario) {
            return $ente->nombreCompleto();
        }

        return $ente?->nombre;
    }
}
