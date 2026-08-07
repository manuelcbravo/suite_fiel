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
use App\Models\Verificacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudController extends Controller
{
    /** Solicitudes que se pintan de golpe dentro de un mismo expediente. */
    private const POR_EXPEDIENTE = 30;

    /**
     * Campos por los que se puede buscar (casillas del buscador legacy).
     * El valor indica si vienen marcados por omisión.
     *
     * @var array<string, bool>
     */
    private const CAMPOS_BUSQUEDA = [
        'solicitante' => true,
        'telefono' => false,
        'celular' => false,
        'organizacion' => false,
        'tel_org' => false,
        'cel_org' => false,
        'solicitud' => true,
        'folio' => true,
        'beneficiario' => false,
    ];

    /**
     * Listado de solicitudes agrupado por solicitante, como el legacy: no
     * devuelve nada hasta que se lanza una búsqueda (texto, dependencia o
     * estatus), y se pinta en dos tablas — personas y organizaciones.
     */
    public function index(Request $request): Response
    {
        $q = $request->string('q')->trim()->toString();
        $dependenciaId = $request->integer('dependencia_id') ?: null;
        $estatus = $request->input('estatus') !== null && $request->input('estatus') !== ''
            ? (int) $request->input('estatus')
            : null;
        $campos = $this->camposBusqueda($request);

        // Entrada directa desde el Directorio: abre el expediente de un
        // solicitante concreto sin pasar por la búsqueda de texto.
        $soloTipo = $request->string('solicitante_tipo')->toString();
        $soloId = $request->integer('solicitante_id') ?: null;
        $solo = $soloId !== null && in_array($soloTipo, ['beneficiario', 'organizacion'], true)
            ? ['tipo' => $soloTipo, 'id' => $soloId]
            : null;

        $buscado = $solo !== null || $q !== '' || $dependenciaId !== null || $estatus !== null;

        $personas = $buscado && ($solo === null || $solo['tipo'] === 'beneficiario')
            ? $this->solicitantes(
                Beneficiario::query()->when($solo, fn (Builder $b) => $b->whereKey($solo['id'])),
                'beneficiario', $q, $campos, $estatus, $dependenciaId, 'page', $solo !== null,
            )
            : null;
        $organizaciones = $buscado && ($solo === null || $solo['tipo'] === 'organizacion')
            ? $this->solicitantes(
                Organizacion::query()->when($solo, fn (Builder $b) => $b->whereKey($solo['id'])),
                'organizacion', $q, $campos, $estatus, $dependenciaId, 'pageOrg', $solo !== null,
            )
            : null;

        return Inertia::render('gestion/solicitudes/index', [
            'buscado' => $buscado,
            'filtros' => [
                'q' => $q,
                'dependencia_id' => $dependenciaId,
                'estatus' => $estatus,
                'campos' => $campos,
                'solicitante_tipo' => $solo['tipo'] ?? null,
                'solicitante_id' => $solo['id'] ?? null,
            ],
            'personas' => $personas ? $this->agrupar($personas, 'beneficiario', $estatus, $dependenciaId) : [],
            'organizaciones' => $organizaciones ? $this->agrupar($organizaciones, 'organizacion', $estatus, $dependenciaId) : [],
            'paginacion' => [
                'personas' => $this->paginacion($personas),
                'organizaciones' => $this->paginacion($organizaciones),
            ],
            'opciones' => [
                'estatus' => collect(Solicitud::ESTATUS)->map(fn (string $n, int $id) => ['id' => $id, 'nombre' => mb_strtoupper($n)])->values(),
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
     * Casillas de "buscar por" activas, con los valores por omisión del legacy.
     *
     * @return array<string, bool>
     */
    private function camposBusqueda(Request $request): array
    {
        if (! $request->has('campos')) {
            return self::CAMPOS_BUSQUEDA;
        }

        /** @var array<string, mixed> $enviados */
        $enviados = (array) $request->input('campos', []);

        return collect(self::CAMPOS_BUSQUEDA)
            ->map(fn (bool $_, string $campo): bool => filter_var($enviados[$campo] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->all();
    }

    /**
     * Solicitantes (beneficiarios u organizaciones) que tienen al menos una
     * solicitud dentro del filtro, y que coinciden con el texto buscado en
     * alguno de los campos marcados.
     *
     * @param  Builder<Model>  $consulta
     * @param  array<string, bool>  $campos
     * @return LengthAwarePaginator<int, Model>
     */
    private function solicitantes(
        Builder $consulta,
        string $tipo,
        string $q,
        array $campos,
        ?int $estatus,
        ?int $dependenciaId,
        string $pageName,
        bool $soloEste = false,
    ): LengthAwarePaginator {
        $esPersona = $tipo === 'beneficiario';

        // Sólo solicitantes con solicitudes que pasen el filtro de estatus/dependencia.
        $consulta->whereHas('solicitudes', fn (Builder $s) => $this->filtrarSolicitudes($s, $estatus, $dependenciaId));

        // Al entrar por un solicitante concreto el texto no filtra: se quiere
        // ver su expediente completo.
        if ($q !== '' && ! $soloEste) {
            $like = '%'.$q.'%';
            /** @var list<string> $palabras */
            $palabras = preg_split('/\s+/', $q) ?: [];

            $consulta->where(function (Builder $w) use ($campos, $esPersona, $like, $palabras, $estatus, $dependenciaId): void {
                // Campos del propio solicitante.
                if ($esPersona) {
                    if ($campos['solicitante']) {
                        // Cada palabra debe aparecer en nombre, paterno o materno,
                        // así "PEREZ JUAN" encuentra igual que "JUAN PEREZ".
                        $w->orWhere(function (Builder $n) use ($palabras): void {
                            foreach ($palabras as $palabra) {
                                $n->where(fn (Builder $p) => $p
                                    ->whereLike('nombre', '%'.$palabra.'%')
                                    ->orWhereLike('paterno', '%'.$palabra.'%')
                                    ->orWhereLike('materno', '%'.$palabra.'%'));
                            }
                        });
                    }
                    if ($campos['telefono']) {
                        $w->orWhereLike('telefono', $like);
                    }
                    if ($campos['celular']) {
                        $w->orWhereLike('celular', $like);
                    }
                } else {
                    if ($campos['organizacion']) {
                        $w->orWhereLike('nombre', $like);
                    }
                    if ($campos['tel_org']) {
                        $w->orWhereLike('telefono', $like);
                    }
                    if ($campos['cel_org']) {
                        $w->orWhereLike('celular', $like);
                    }
                }

                // Campos de sus solicitudes.
                foreach (['solicitud' => ['solicitud'], 'folio' => ['folio', 'folio_sistema'], 'beneficiario' => ['desc_bene']] as $campo => $columnas) {
                    if (! $campos[$campo]) {
                        continue;
                    }

                    $w->orWhereHas('solicitudes', function (Builder $s) use ($columnas, $like, $estatus, $dependenciaId): void {
                        $this->filtrarSolicitudes($s, $estatus, $dependenciaId);
                        $s->where(function (Builder $sub) use ($columnas, $like): void {
                            foreach ($columnas as $columna) {
                                $sub->orWhereLike($columna, $like);
                            }
                        });
                    });
                }
            });
        }

        $consulta->with(['localidad:id,nombre']);

        if ($esPersona) {
            $consulta->orderBy('paterno')->orderBy('materno')->orderBy('nombre');
        } else {
            $consulta->orderBy('nombre');
        }

        return $consulta->paginate(15, ['*'], $pageName)->withQueryString();
    }

    /**
     * Solicitudes de un expediente, por tandas. Hay solicitantes con cientos
     * (una cuenta institucional llega a 597); pintarlas todas de golpe congela
     * el navegador, así que se sirven de 30 en 30 y el resto se pide al vuelo.
     *
     * @return list<array<string, mixed>>
     */
    private function solicitudesDelExpediente(
        Model $ente,
        string $nombre,
        ?int $estatus,
        ?int $dependenciaId,
        int $desde = 0,
    ): array {
        return $ente->solicitudes()
            ->tap(fn (Builder $s) => $this->filtrarSolicitudes($s, $estatus, $dependenciaId))
            ->with(['concepto:id,nombre', 'municipioResp:id,nombre', 'rubros:id', 'sectores:id'])
            ->withCount('seguimientos')
            ->orderByDesc('folio_sistema')
            ->orderByDesc('id')
            ->skip($desde)
            ->take(self::POR_EXPEDIENTE)
            ->get()
            ->map(fn (Solicitud $s) => $this->serializar($s, $nombre))
            ->values()
            ->all();
    }

    /**
     * Siguiente tanda de solicitudes de un expediente (el "ver más" del listado).
     */
    public function solicitudesDe(Request $request, string $tipo, int $id): JsonResponse
    {
        $ente = $tipo === 'organizacion'
            ? Organizacion::query()->findOrFail($id)
            : Beneficiario::query()->findOrFail($id);

        $nombre = $ente instanceof Beneficiario ? $ente->nombreCompleto() : (string) $ente->nombre;

        return response()->json($this->solicitudesDelExpediente(
            $ente,
            $nombre,
            $request->input('estatus') !== null && $request->input('estatus') !== ''
                ? (int) $request->input('estatus')
                : null,
            $request->integer('dependencia_id') ?: null,
            max(0, $request->integer('desde')),
        ));
    }

    /** Filtro de estatus / dependencia turnada que comparten todas las consultas. */
    private function filtrarSolicitudes(Builder $s, ?int $estatus, ?int $dependenciaId): void
    {
        if ($estatus !== null) {
            $s->where('status', $estatus);
        }

        if ($dependenciaId !== null) {
            $s->whereHas('seguimientos', fn (Builder $sg) => $sg->where('dependencia_id', $dependenciaId));
        }
    }

    /**
     * Arma la fila padre (solicitante) con sus solicitudes debajo.
     *
     * @param  LengthAwarePaginator<int, Model>  $solicitantes
     * @return list<array<string, mixed>>
     */
    private function agrupar(LengthAwarePaginator $solicitantes, string $tipo, ?int $estatus, ?int $dependenciaId): array
    {
        return collect($solicitantes->items())
            ->map(function (Model $ente) use ($tipo, $estatus, $dependenciaId): array {
                $nombre = $ente instanceof Beneficiario ? $ente->nombreCompleto() : (string) $ente->nombre;

                $total = $ente->solicitudes()
                    ->tap(fn (Builder $s) => $this->filtrarSolicitudes($s, $estatus, $dependenciaId))
                    ->count();

                return [
                    'id' => $ente->id,
                    'tipo' => $tipo,
                    'nombre' => $nombre,
                    'localidad' => $ente->localidad?->nombre,
                    'telefonos' => trim(implode(' / ', array_filter([$ente->celular, $ente->telefono]))) ?: null,
                    'solicitudes_total' => $total,
                    'solicitudes' => $this->solicitudesDelExpediente(
                        $ente, $nombre, $estatus, $dependenciaId,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  LengthAwarePaginator<int, Model>|null  $p
     * @return array<string, mixed>|null
     */
    private function paginacion(?LengthAwarePaginator $p): ?array
    {
        if (! $p) {
            return null;
        }

        return [
            'total' => $p->total(),
            'currentPage' => $p->currentPage(),
            'lastPage' => $p->lastPage(),
            'prevUrl' => $p->previousPageUrl(),
            'nextUrl' => $p->nextPageUrl(),
        ];
    }

    /**
     * Registra la atención/resolución de una solicitud turnada (replica
     * `seg_turnar.php` / `seg_resolver.php`):
     *  - temporal → estatus_resp 4, avance 0, solicitud en "Respuesta de área"
     *  - rápida   → estatus_resp 6, avance 2, solicitud en "Atención rápida"
     *  - resolver → captura el apoyo entregado; si está completo pasa a
     *    "Atendida" (5), si falta algo se queda guardada sin cambiar estatus.
     */
    public function atender(AtenderSolicitudRequest $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validated();
        $decision = $data['decision'];

        $seguimiento = $solicitud->seguimientos()->latest('id')->first();

        if ($decision !== 'resolver') {
            // Respuesta temporal / atención rápida: sólo localidad, fecha y concepto.
            $rapida = $decision === 'rapida';

            $solicitud->fill([
                'status' => $rapida ? 6 : 4,
                'localidad_resp_id' => $data['localidad_resp_id'] ?? null,
                'concepto_id' => $data['concepto_id'] ?? null,
                'fecha_resp' => $data['fecha_resp'] ?? null,
            ])->save();

            $seguimiento?->update([
                'respuesta' => $data['respuesta'] ?? $seguimiento->respuesta,
                'avance' => $rapida ? 2 : 0,
                'estatus_resp' => $rapida ? 6 : 4,
                'fecha_respuesta' => now(),
                'respuesta_de_id' => $request->user()?->id,
            ]);

            return back()->with('success', $rapida
                ? 'Atención rápida registrada.'
                : 'Respuesta temporal registrada.');
        }

        // Resolver: captura completa del apoyo entregado.
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

        $rubros = $data['rubros'] ?? [];
        $sectores = $data['sectores'] ?? [];

        // El legacy sólo cierra la solicitud si la captura quedó completa.
        $completa = $this->capturaCompleta($solicitud, $rubros, $sectores);

        if ($completa) {
            $solicitud->status = 5;
        }

        $solicitud->save();
        $solicitud->rubros()->sync($rubros);
        $solicitud->sectores()->sync($sectores);

        $seguimiento?->update([
            'respuesta' => $data['respuesta'] ?? $seguimiento->respuesta,
            'avance' => 1,
            'respuesta_de_id' => $request->user()?->id,
            ...($completa ? ['estatus_resp' => 5, 'fecha_respuesta' => now()] : []),
        ]);

        return back()->with('success', $completa
            ? 'Solicitud atendida.'
            : 'Información guardada. Falta capturar datos para poder cerrarla.');
    }

    /**
     * @param  list<int>  $rubros
     * @param  list<int>  $sectores
     */
    private function capturaCompleta(Solicitud $solicitud, array $rubros, array $sectores): bool
    {
        $obligatorios = [
            $solicitud->cantidad, $solicitud->num_bene, $solicitud->apoyo, $solicitud->tipo,
            $solicitud->concepto_id, $solicitud->municipio_resp_id, $solicitud->localidad_resp_id,
            $solicitud->fecha_resp, $solicitud->folio_resp,
        ];

        foreach ($obligatorios as $valor) {
            if ($valor === null || $valor === '' || $valor === '0' || $valor === 0) {
                return false;
            }
        }

        return $rubros !== [] && $sectores !== [];
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

        // El folio de sistema del legacy es consecutivo por solicitud.
        if ($solicitud->wasRecentlyCreated && ! $solicitud->folio_sistema) {
            $solicitud->update(['folio_sistema' => (string) $solicitud->id]);
        }

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
     * Seguimientos (turnado/respuesta) de una solicitud, para el historial.
     */
    public function seguimientos(Solicitud $solicitud): JsonResponse
    {
        $items = $solicitud->seguimientos()
            ->with(['dependencia:id,nombre', 'area:id,nombre', 'creadoPor:id,name'])
            ->latest('id')
            ->get()
            ->map(fn ($sg): array => [
                'id' => $sg->id,
                'estatus' => $sg->estatus,
                'estatus_label' => mb_strtoupper($sg->estatusLabel()),
                'estatus_resp_label' => $sg->estatus_resp !== null
                    ? mb_strtoupper(Solicitud::ESTATUS[$sg->estatus_resp] ?? (string) $sg->estatus_resp)
                    : null,
                'dependencia_id' => $sg->dependencia_id,
                'dependencia' => $sg->dependencia?->nombre,
                'area_id' => $sg->area_id,
                'area' => $sg->area?->nombre,
                'instruccion' => $sg->instruccion,
                'comentario' => $sg->comentario,
                'respuesta' => $sg->respuesta,
                'responsable' => $sg->responsable,
                'avance' => $sg->avance,
                'autor' => $sg->creadoPor?->name,
                'fecha' => $sg->created_at?->format('d/m/Y H:i'),
                'fecha_respuesta' => $sg->fecha_respuesta?->format('d/m/Y H:i'),
            ]);

        return response()->json($items);
    }

    /**
     * Verificaciones (satisfacción) de una solicitud atendida.
     */
    public function verificaciones(Solicitud $solicitud): JsonResponse
    {
        $items = $solicitud->verificaciones()
            ->with('creadoPor:id,name')
            ->latest('id')
            ->get()
            ->map(fn (Verificacion $v): array => [
                'id' => $v->id,
                'fecha' => $v->fecha?->toDateString(),
                'atendido' => $v->atendido,
                'satisfecho_label' => Verificacion::SATISFACCION[$v->satisfecho] ?? null,
                'comentario' => $v->comentario,
                'autor' => $v->creadoPor?->name,
            ]);

        return response()->json($items);
    }

    public function registrarVerificacion(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['nullable', 'date'],
            'comentario' => ['required', 'string', 'max:2000'],
            'atendido' => ['nullable', 'integer', 'in:0,1'],
            'satisfecho' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $solicitud->verificaciones()->create([
            'fecha' => $data['fecha'] ?? now()->toDateString(),
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
    private function serializar(Solicitud $s, string $nombreSolicitante): array
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
            'bene_final' => $s->bene_final,
            // Columna "Nombre del beneficiario" del listado legacy.
            'beneficiario_nombre' => $s->bene_final === 1 ? $nombreSolicitante : $s->desc_bene,
            'num_bene' => $s->num_bene,
            'status' => $s->status,
            'status_label' => mb_strtoupper(Solicitud::ESTATUS[$s->status] ?? (string) $s->status),
            'control_administrativo' => $s->control_administrativo,
            'notificacion' => (bool) $s->notificacion,
            'prioridad' => $s->prioridad,
            'fecha_recepcion' => $s->fecha_recepcion?->toDateString(),
            'fecha_comp' => $s->fecha_comp,
            'concepto_id' => $s->concepto_id,
            'procedencia_id' => $s->procedencia_id,
            'unidad_medida_id' => $s->tipo,
            'origen_recurso_id' => is_numeric($s->origen) ? (int) $s->origen : null,
            'folio_resp' => $s->folio_resp,
            'fecha_resp' => $s->fecha_resp,
            'latitud' => $s->latitud,
            'longitud' => $s->longitud,
            'imagen' => $s->imagen,
            'solicitante_tipo' => $s->solicitante_type === Organizacion::class ? 'organizacion' : 'beneficiario',
            'solicitante_id' => $s->solicitante_id,
            'solicitante_nombre' => $nombreSolicitante,
            'municipio_resp_nombre' => $s->municipioResp?->nombre,
            'estado_resp_id' => $s->estado_resp_id,
            'municipio_resp_id' => $s->municipio_resp_id,
            'localidad_resp_id' => $s->localidad_resp_id,
            'rubros' => $s->rubros->pluck('id'),
            'sectores' => $s->sectores->pluck('id'),
            'seguimientos_count' => $s->seguimientos_count,
        ];
    }
}
