<?php

namespace App\Http\Controllers\Obra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Obra\UpsertEstimacionRequest;
use App\Http\Requests\Obra\UpsertObraRequest;
use App\Models\CatConcepto;
use App\Models\CatEje;
use App\Models\CatEstado;
use App\Models\CatPedEje;
use App\Models\CatPedSubeje;
use App\Models\CatSector;
use App\Models\CatSubeje;
use App\Models\CatUnidadMedida;
use App\Models\Obra;
use App\Models\ObraEstimacion;
use App\Models\Proveedor;
use App\Models\Solicitud;
use App\Support\CamposObra;
use App\Support\DocumentosObra;
use App\Support\EstimacionesObra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ObraController extends Controller
{
    public function index(Request $request): Response
    {
        $f = $this->filtros($request);
        $obras = $this->query($f)->paginate(20)->withQueryString();

        return Inertia::render('obra/index', [
            'filtros' => $f,
            'opciones' => $this->opciones(),
            'etapas' => collect(Obra::ETAPAS)
                ->map(fn (string $label, string $clave) => ['clave' => $clave, 'label' => $label])
                ->values(),
            'obras' => $obras->getCollection()->map(fn (Obra $o): array => $this->fila($o))->values(),
            'paginacion' => $this->paginacion($obras),
        ]);
    }

    public function store(UpsertObraRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $obra = isset($data['id'])
            ? Obra::query()->findOrFail((int) $data['id'])
            : new Obra;

        unset($data['id']);

        if (! $obra->exists) {
            $obra->created_by = $request->user()?->id;
        }

        $obra->fill($data)->save();

        // El legacy marca la solicitud como importada al crear su expediente.
        if ($obra->wasRecentlyCreated && $obra->solicitud_id !== null) {
            Solicitud::query()->whereKey($obra->solicitud_id)
                ->update(['convertida_obra' => Solicitud::OBRA_IMPORTADA]);
        }

        return back()->with('success', $obra->wasRecentlyCreated
            ? 'Obra creada correctamente.'
            : 'Ficha técnica actualizada.');
    }

    public function destroy(Obra $obra): RedirectResponse
    {
        // Al borrar el expediente la solicitud vuelve a estar sin obra,
        // igual que en `obrapublica/eliminar_obra.php`.
        if ($obra->solicitud_id !== null) {
            Solicitud::query()->whereKey($obra->solicitud_id)
                ->update(['convertida_obra' => Solicitud::OBRA_NO]);
        }

        $obra->delete();

        return back()->with('success', 'Obra eliminada correctamente.');
    }

    /** La ficha técnica completa, que el listado no carga por peso. */
    public function ficha(Obra $obra): array
    {
        $obra->load('localidad.municipio:id,nombre');

        return $obra->only([
            'id', 'solicitud_id', 'num_obra', 'nombre', 'unidad_medida_id', 'concepto_id',
            'sector_id', 'dependencia', 'prop_financ', 'monto_inv', 'num_benef',
            'descripcion', 'metas', 'calle_num', 'localidad_id', 'referencia_mapa',
            'latitud', 'longitud', 'col_beneficiados', 'pmd_eje_id', 'pmd_subeje_id',
            'ped_eje_id', 'ped_subeje_id', 'analisis_problematica', 'analisis_sinobra',
            'alternativa_solucion', 'asp_tecnicos', 'asp_ambientales', 'asp_legales',
            'asp_sociales', 'rentab_social', 'impacto_social', 'descrip_renta_impac',
            'factibilidad', 'conclusion', 'ficha_completo',
        ]) + [
            // La cascada estado → municipio → localidad necesita los tres
            // niveles para poder preseleccionar; la obra sólo guarda el último.
            'estado_id' => $obra->localidad?->estado_id,
            'municipio_id' => $obra->localidad?->municipio_id,
            'localidad_nombre' => $obra->localidad?->nombre,
            'municipio_nombre' => $obra->localidad?->municipio?->nombre,
        ];
    }

    /** Los papeles de una etapa, con lo que ya se capturó de cada uno. */
    public function etapa(Obra $obra, string $etapa): array
    {
        abort_unless(isset(Obra::ETAPAS[$etapa]), 404);

        $obra->load(['autorizacion', 'adjudicacion.empresa:id,nombre', 'ejecucion', 'cierre']);

        $capturado = $obra->documentos()
            ->where('etapa', $etapa)
            ->get()
            ->keyBy('clave');

        $relacion = CamposObra::relacion($etapa);
        $registro = $relacion === null ? null : $obra->{$relacion};

        return [
            'etapa' => $etapa,
            'titulo' => Obra::ETAPAS[$etapa],
            'completo' => (bool) $obra->{"{$etapa}_completo"},
            'catalogos' => CamposObra::CATALOGOS,
            'grupos' => collect(CamposObra::grupos($etapa))
                ->map(fn (array $campos, string $titulo): array => [
                    'titulo' => $titulo,
                    'campos' => collect($campos)
                        ->map(fn (array $def, string $campo): array => [
                            'campo' => $campo,
                            'label' => $def[0],
                            'tipo' => $def[1],
                            'catalogo' => $def[2] ?? null,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'datos' => collect(CamposObra::campos($etapa))
                ->map(function (array $def, string $campo) use ($registro) {
                    $valor = $registro?->{$campo};

                    // Las fechas viajan como 'YYYY-MM-DD' para el input date.
                    return $def[1] === 'fecha'
                        ? $valor?->format('Y-m-d')
                        : ($valor === null ? '' : (string) $valor);
                })
                ->all(),
            'empresa_nombre' => $etapa === 'adjudicacion'
                ? $obra->adjudicacion?->empresa?->nombre
                : null,
            'documentos' => collect(DocumentosObra::de($etapa))
                ->map(fn (string $label, string $clave): array => [
                    'clave' => $clave,
                    'label' => $label,
                    'presente' => $capturado[$clave]->presente ?? null,
                    'observacion' => $capturado[$clave]->observacion ?? '',
                ])
                ->values()
                ->all(),
        ];
    }

    public function guardarEtapa(Request $request, Obra $obra, string $etapa): RedirectResponse
    {
        abort_unless(isset(Obra::ETAPAS[$etapa]), 404);

        // Los campos vacíos del formulario llegan como '' y hay que tratarlos
        // como "sin dato" antes de validar, o una fecha borrada se leería como
        // fecha inválida.
        $request->merge(['datos' => $this->datosNormalizados($request)]);

        $data = $request->validate([
            'completo' => ['boolean'],
            'datos' => ['array'],
            ...$this->reglasDatos($etapa),
            'documentos' => ['array'],
            'documentos.*.clave' => ['required', 'string'],
            'documentos.*.presente' => ['nullable', 'boolean'],
            'documentos.*.observacion' => ['nullable', 'string'],
        ]);

        $this->guardarDatosEtapa($obra, $etapa, $data['datos'] ?? []);

        $validas = DocumentosObra::claves($etapa);

        foreach ($data['documentos'] ?? [] as $doc) {
            if (! in_array($doc['clave'], $validas, true)) {
                continue;
            }

            $presente = $doc['presente'] ?? null;
            $observacion = trim((string) ($doc['observacion'] ?? '')) ?: null;

            // Sin acuse ni observación no hay nada que guardar: el renglón se
            // borra para que la tabla sólo tenga papeles con dato.
            if ($presente === null && $observacion === null) {
                $obra->documentos()->where('clave', $doc['clave'])->delete();

                continue;
            }

            $obra->documentos()->updateOrCreate(
                ['clave' => $doc['clave']],
                ['etapa' => $etapa, 'presente' => $presente, 'observacion' => $observacion],
            );
        }

        $obra->update(["{$etapa}_completo" => (bool) ($data['completo'] ?? false)]);

        return back()->with('success', Obra::ETAPAS[$etapa].' actualizada.');
    }

    /**
     * Los `datos` del formulario con los vacíos convertidos a null.
     *
     * @return array<string,mixed>
     */
    private function datosNormalizados(Request $request): array
    {
        /** @var array<string,mixed> $datos */
        $datos = (array) $request->input('datos', []);

        return array_map(function ($valor) {
            $valor = is_string($valor) ? trim($valor) : $valor;

            return $valor === '' ? null : $valor;
        }, $datos);
    }

    /**
     * Reglas de validación de los campos de la etapa, derivadas del propio
     * catálogo: una fecha tiene que ser una fecha y una lista de opciones sólo
     * acepta sus claves. Sin esto un texto cualquiera llegaba hasta el cast de
     * Eloquent y tumbaba la petición con un 500.
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglasDatos(string $etapa): array
    {
        $reglas = [];

        foreach (CamposObra::campos($etapa) as $campo => $def) {
            $reglas["datos.{$campo}"] = match ($def[1]) {
                'fecha' => ['nullable', 'date'],
                'opciones' => ['nullable', 'integer', Rule::in(array_keys(CamposObra::CATALOGOS[$def[2]] ?? []))],
                'empresa' => ['nullable', 'integer', Rule::exists('tbl_proveedores', 'id')],
                'area' => ['nullable', 'string'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $reglas;
    }

    /**
     * Guarda los campos propios de la etapa en su tabla 1:1, creándola si es
     * la primera captura. Sólo se aceptan los campos del catálogo.
     *
     * @param  array<string,mixed>  $datos
     */
    private function guardarDatosEtapa(Obra $obra, string $etapa, array $datos): void
    {
        $relacion = CamposObra::relacion($etapa);

        if ($relacion === null || $datos === []) {
            return;
        }

        $limpio = [];

        foreach (CamposObra::campos($etapa) as $campo => $def) {
            if (! array_key_exists($campo, $datos)) {
                continue;
            }

            $valor = $datos[$campo];
            $valor = is_string($valor) ? trim($valor) : $valor;
            $limpio[$campo] = ($valor === '' || $valor === null) ? null : $valor;
        }

        if ($limpio === []) {
            return;
        }

        $registro = $obra->{$relacion};

        if ($registro === null) {
            $obra->{$relacion}()->create($limpio);

            return;
        }

        $registro->update($limpio);
    }

    /** Las estimaciones de la obra, en el orden en que se presentaron. */
    public function estimaciones(Obra $obra): array
    {
        $obra->load('adjudicacion:id,obra_id,monto_contrato');

        return [
            'obra' => [
                'id' => $obra->id,
                'nombre' => $obra->nombre,
                'monto_contrato' => $obra->adjudicacion?->monto_contrato,
            ],
            'catalogos' => EstimacionesObra::CATALOGOS,
            'documentos' => EstimacionesObra::DOCUMENTOS,
            // El orden es por el número de estimación leído como número (la 10
            // va después de la 9), y las que no traen número van al final. Se
            // ordena en PHP —son unas decenas por obra— para no atar el
            // endpoint a la sintaxis de un motor de base de datos concreto.
            'estimaciones' => $obra->estimaciones()
                ->orderBy('id')
                ->get()
                ->sortBy(fn (ObraEstimacion $e): array => [
                    $this->ordenEstimacion($e->num_estimacion),
                    $e->id,
                ])
                ->map(fn (ObraEstimacion $e): array => $this->filaEstimacion($e))
                ->values()
                ->all(),
        ];
    }

    /** Los dígitos del número de estimación; sin ellos, va al final. */
    private function ordenEstimacion(?string $num): int
    {
        $digitos = preg_replace('/\D+/', '', (string) $num);

        return $digitos === '' || $digitos === null ? PHP_INT_MAX : (int) $digitos;
    }

    public function guardarEstimacion(UpsertEstimacionRequest $request, Obra $obra): RedirectResponse
    {
        $data = $request->validated();

        $estimacion = isset($data['id'])
            ? $obra->estimaciones()->findOrFail((int) $data['id'])
            : $obra->estimaciones()->make();

        unset($data['id']);

        $estimacion->fill($data)->save();

        return back()->with('success', $estimacion->wasRecentlyCreated
            ? 'Estimación agregada.'
            : 'Estimación actualizada.');
    }

    public function eliminarEstimacion(Obra $obra, ObraEstimacion $estimacion): RedirectResponse
    {
        abort_unless($estimacion->obra_id === $obra->id, 404);

        $estimacion->delete();

        return back()->with('success', 'Estimación eliminada.');
    }

    /** @return array<string,mixed> */
    private function filaEstimacion(ObraEstimacion $e): array
    {
        return [
            'id' => $e->id,
            'num_estimacion' => $e->num_estimacion ?? '',
            'tipo' => $e->tipo,
            'superintendente' => $e->superintendente ?? '',
            'residente' => $e->residente ?? '',
            'fecha_recepcion' => $e->fecha_recepcion?->format('Y-m-d'),
            'periodo_inicio' => $e->periodo_inicio?->format('Y-m-d'),
            'periodo_termino' => $e->periodo_termino?->format('Y-m-d'),
            'monto' => $e->monto ?? '',
            'calidad_obra' => $e->calidad_obra ?? '',
            'estatus' => $e->estatus ?? '',
            'monto_aprobado' => $e->monto_aprobado ?? '',
            'fecha_aprobacion' => $e->fecha_aprobacion?->format('Y-m-d'),
            'num_factura' => $e->num_factura ?? '',
            'fecha_factura' => $e->fecha_factura?->format('Y-m-d'),
            'importe_pagado' => $e->importe_pagado ?? '',
            'fecha_pago' => $e->fecha_pago?->format('Y-m-d'),
            'avance_fisico' => $e->avance_fisico ?? '',
            'avance_financiero' => $e->avance_financiero ?? '',
            'doc_obligatoria' => $e->doc_obligatoria ?? '',
            'completo' => (bool) $e->completo,
        ];
    }

    /** Proveedores del directorio, para elegir a la contratista. */
    public function proveedores(Request $request): array
    {
        $q = trim($request->string('q')->toString());

        if (mb_strlen($q) < 2) {
            return [];
        }

        return Proveedor::query()
            ->where(fn ($sub) => $sub
                ->whereLike('nombre', "%{$q}%")
                ->orWhereLike('rfc', "%{$q}%"))
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'nombre'])
            ->all();
    }

    /** Solicitudes para el alta, como el "Importar solicitud" del legacy. */
    public function solicitudes(Request $request): array
    {
        $q = trim($request->string('q')->toString());

        if (mb_strlen($q) < 2) {
            return [];
        }

        return Solicitud::query()
            ->whereDoesntHave('obra')
            ->where(fn ($sub) => $sub
                ->whereLike('solicitud', "%{$q}%")
                ->orWhereLike('folio', "%{$q}%")
                ->orWhereLike('folio_sistema', "%{$q}%"))
            ->orderByDesc('fecha_recepcion')
            ->limit(20)
            ->get(['id', 'folio', 'folio_sistema', 'solicitud'])
            ->map(fn (Solicitud $s): array => [
                'id' => $s->id,
                'nombre' => trim(($s->folio_sistema ?: $s->folio).' · '.mb_substr((string) $s->solicitud, 0, 80)),
            ])
            ->all();
    }

    /** @param array<string,mixed> $f */
    private function query(array $f)
    {
        return Obra::query()
            ->with([
                'localidad:id,nombre',
                'solicitud:id,folio,folio_sistema,solicitud',
                'autorizacion:id,obra_id,nombre_obra_aut,mod_ejecucion',
            ])
            ->when($f['q'] !== '', function ($q) use ($f): void {
                $texto = "%{$f['q']}%";

                $q->where(fn ($sub) => $sub
                    ->whereLike('nombre', $texto)
                    ->orWhereLike('num_obra', $texto)
                    ->orWhereHas('solicitud', fn ($s) => $s
                        ->whereLike('folio_sistema', $texto)
                        ->orWhereLike('solicitud', $texto))
                    ->orWhereHas('localidad', fn ($l) => $l->whereLike('nombre', $texto)));
            })
            ->when($f['etapa'], fn ($q) => $q->where("{$f['etapa']}_completo", true))
            ->when($f['pendiente'], fn ($q) => $q->where("{$f['pendiente']}_completo", false))
            // El legacy lista por id ascendente.
            ->orderBy('id');
    }

    /**
     * @return array<string,mixed>
     */
    private function fila(Obra $o): array
    {
        return [
            'id' => $o->id,
            // El nombre se toma del más avanzado que exista, como el legacy:
            // el autorizado manda sobre el capturado, y ése sobre el texto de
            // la solicitud de origen.
            'nombre' => $o->autorizacion?->nombre_obra_aut
                ?: ($o->nombre ?: $o->solicitud?->solicitud),
            'num_obra' => $o->num_obra,
            'localidad' => $o->localidad?->nombre,
            'folio' => $o->solicitud?->folio_sistema ?: $o->solicitud?->folio,
            'solicitud_id' => $o->solicitud_id,
            // La adjudicación y la ejecución sólo se habilitan cuando la
            // autorización ya definió la modalidad de ejecución.
            'con_modalidad' => filled($o->autorizacion?->mod_ejecucion),
            'etapas' => collect(Obra::ETAPAS)
                ->map(fn (string $label, string $clave) => $o->{"{$clave}_completo"})
                ->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function filtros(Request $request): array
    {
        $etapa = $request->string('etapa')->toString();
        $pendiente = $request->string('pendiente')->toString();
        $valida = fn (string $v): ?string => isset(Obra::ETAPAS[$v]) ? $v : null;

        return [
            'q' => trim($request->string('q')->toString()),
            'etapa' => $valida($etapa),
            'pendiente' => $valida($pendiente),
        ];
    }

    /** @return array<string,mixed> */
    private function opciones(): array
    {
        return [
            'unidades' => CatUnidadMedida::query()->orderBy('nombre')->get(['id', 'nombre']),
            'conceptos' => CatConcepto::query()->orderBy('nombre')->get(['id', 'nombre']),
            'sectores' => CatSector::query()->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
            'pmdEjes' => CatEje::query()->orderBy('nombre')->get(['id', 'nombre']),
            'pmdSubejes' => CatSubeje::query()->orderBy('nombre')->get(['id', 'nombre', 'eje_id']),
            'pedEjes' => CatPedEje::query()->orderBy('nombre')->get(['id', 'nombre']),
            'pedSubejes' => CatPedSubeje::query()->orderBy('nombre')->get(['id', 'nombre', 'eje_id']),
        ];
    }

    /**
     * @param  LengthAwarePaginator  $p
     * @return array<string,mixed>
     */
    private function paginacion($p): array
    {
        return [
            'total' => $p->total(),
            'currentPage' => $p->currentPage(),
            'lastPage' => $p->lastPage(),
            'prevUrl' => $p->previousPageUrl(),
            'nextUrl' => $p->nextPageUrl(),
        ];
    }
}
