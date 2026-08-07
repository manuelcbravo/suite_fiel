<?php

namespace App\Http\Controllers\Tableros;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Denuncia;
use App\Models\Detenido;
use App\Models\Evento;
use App\Models\Obra;
use App\Models\Organizacion;
use App\Models\Solicitud;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableros (Módulo 9) — replican la estructura de los tableros legacy
 * (index.php = Ejecutivo, tablero.php = Gestión + Financiero). Se omite
 * Obra pública. Codificación de `status` de la solicitud:
 * 0 Capturada · 1 Turnada · 2 No aprobada · 3 Para resolver ·
 * 4 Respuesta de área · 5 Atendida · 6 Atención rápida.
 */
class TableroController extends Controller
{
    /** Expresión SQL: monto como numérico (0 si no es numérico). */
    private const MONTO = "(CASE WHEN monto ~ '^[0-9]+(\\.[0-9]+)?\$' THEN monto::numeric ELSE 0 END)";

    // ---------------------------------------------------------------- Ejecutivo
    public function ejecutivo(): Response
    {
        $total = $this->cuenta([0, 1, 2, 3, 4, 5, 6]);
        $concluidas = $this->cuenta([2, 5, 6]);

        return Inertia::render('tableros/ejecutivo', [
            'agendaHoy' => $this->eventosDe('CURRENT_DATE'),
            'agendaManana' => $this->eventosDe("CURRENT_DATE + INTERVAL '1 day'"),
            'total' => $total,
            'estatus' => [
                ['Capturadas', $this->cuenta([0])],
                ['Turnadas', $this->cuenta([1, 4])],
                ['Por resolver', $this->cuenta([3])],
                ['No aprobadas', $this->cuenta([2])],
                ['Atendidas', $this->cuenta([5])],
                ['Atención rápida', $this->cuenta([6])],
            ],
            'porDependencia' => $this->porDependencia([1, 3, 4]),
            'cumplimiento' => $total > 0 ? round($concluidas / $total * 100) : 0,
        ]);
    }

    // ---------------------------------------------------------------- Seguridad
    public function obra(): Response
    {
        // Los siete acuses del legacy, en el mismo orden de su statsBar.
        $etapas = Obra::query()
            ->selectRaw('
                count(*) as total,
                count(*) filter (where ficha_completo) as ficha,
                count(*) filter (where expediente_completo) as expediente,
                count(*) filter (where autorizacion_completo) as autorizacion,
                count(*) filter (where adjudicacion_completo) as adjudicacion,
                count(*) filter (where ejecucion_completo) as ejecucion,
                count(*) filter (where conclusion_completo) as conclusion,
                count(*) filter (where entrega_completo) as entrega
            ')
            ->first();

        $total = (int) ($etapas->total ?? 0);

        return Inertia::render('tableros/obra', [
            'resumen' => [
                'total' => $total,
                'ficha' => (int) $etapas->ficha,
                'expediente' => (int) $etapas->expediente,
                'autorizacion' => (int) $etapas->autorizacion,
                'adjudicacion' => (int) $etapas->adjudicacion,
                'ejecucion' => (int) $etapas->ejecucion,
                'conclusion' => (int) $etapas->conclusion,
                'entrega' => (int) $etapas->entrega,
            ],
            // Qué tanto del padrón llegó hasta la entrega-recepción.
            'cumplimiento' => $total > 0
                ? round(((int) $etapas->entrega) * 100 / $total, 1)
                : 0,
            'porEtapa' => [
                ['Sin ficha cerrada', $total - (int) $etapas->ficha],
                ['Hasta ficha o expediente', max(0, (int) $etapas->ficha - (int) $etapas->adjudicacion)],
                ['Adjudicadas, sin entregar', max(0, (int) $etapas->adjudicacion - (int) $etapas->entrega)],
                ['Entregadas', (int) $etapas->entrega],
            ],
            'topLocalidad' => $this->inversionPorLocalidad(),
            'obras' => $this->obrasUbicadas(),
        ]);
    }

    /**
     * Inversión por localidad. `monto_inv` es texto en el legacy (con comas y
     * basura), así que se limpia antes de sumar y se descarta lo que no sea
     * un número.
     *
     * @return list<array{0: string, 1: float}>
     */
    private function inversionPorLocalidad(): array
    {
        return DB::table('tbl_obras as o')
            ->join('cat_localidades as l', 'l.id', '=', 'o.localidad_id')
            ->whereNull('o.deleted_at')
            ->whereRaw("replace(replace(coalesce(o.monto_inv, ''), ',', ''), '$', '') ~ '^[0-9]+(\\.[0-9]+)?$'")
            ->groupBy('l.nombre')
            ->selectRaw("l.nombre, sum(replace(replace(o.monto_inv, ',', ''), '$', '')::numeric) as monto")
            ->orderByDesc('monto')
            ->limit(10)
            ->get()
            ->map(fn ($r): array => [$r->nombre, (float) $r->monto])
            ->all();
    }

    /**
     * Las obras con coordenada, con la etapa que alcanzaron para el color.
     *
     * @return list<array<string,mixed>>
     */
    private function obrasUbicadas(): array
    {
        return Obra::query()
            ->with('autorizacion:id,obra_id,nombre_obra_aut')
            // El texto tiene que parecer un número: hay registros con "NaN",
            // que al castear se vuelven 0 y mandan el alfiler al Atlántico.
            ->whereRaw("latitud ~ '^-?[0-9]+(\\.[0-9]+)?\$'")
            ->whereRaw("longitud ~ '^-?[0-9]+(\\.[0-9]+)?\$'")
            ->get(['id', 'num_obra', 'nombre', 'latitud', 'longitud', 'adjudicacion_completo', 'entrega_completo'])
            ->map(fn (Obra $o): array => [
                'id' => $o->num_obra ?: $o->id,
                'lat' => (float) $o->latitud,
                'lng' => (float) $o->longitud,
                'resumen' => Str::limit((string) ($o->autorizacion?->nombre_obra_aut ?: $o->nombre), 90),
                'etapa' => match (true) {
                    $o->entrega_completo => 'Entregada',
                    $o->adjudicacion_completo => 'En ejecución',
                    default => 'Sin adjudicar',
                },
            ])
            // Y tiene que ser un punto de la Tierra que no sea el (0, 0).
            ->filter(fn (array $p): bool => abs($p['lat']) <= 90
                && abs($p['lng']) <= 180
                && ($p['lat'] !== 0.0 || $p['lng'] !== 0.0))
            ->values()
            ->all();
    }

    public function seguridad(): Response
    {
        $porEtapa = Denuncia::query()
            ->selectRaw('
                count(*) filter (where not con_atencion and not con_termino) as recibidas,
                count(*) filter (where con_atencion and not con_termino) as en_atencion,
                count(*) filter (where con_termino) as concluidas,
                count(*) as total
            ')
            ->first();

        return Inertia::render('tableros/seguridad', [
            'resumen' => [
                'total' => (int) ($porEtapa->total ?? 0),
                'recibidas' => (int) ($porEtapa->recibidas ?? 0),
                'enAtencion' => (int) ($porEtapa->en_atencion ?? 0),
                'concluidas' => (int) ($porEtapa->concluidas ?? 0),
                'detenidos' => Detenido::query()->count(),
            ],
            'porEtapa' => [
                ['Recepción', (int) ($porEtapa->recibidas ?? 0)],
                ['En atención', (int) ($porEtapa->en_atencion ?? 0)],
                ['Concluidas', (int) ($porEtapa->concluidas ?? 0)],
            ],
            'porDelito' => $this->porDelito(),
            'incidencias' => $this->incidenciasUbicadas(),
        ]);
    }

    /**
     * Los presuntos delitos más frecuentes.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function porDelito(): array
    {
        return Denuncia::query()
            ->join('cat_tipos_incidencia as t', 't.id', '=', 'tbl_denuncias.tipo_incidencia_id')
            ->selectRaw('t.nombre, count(*) as n')
            ->groupBy('t.nombre')
            ->orderByDesc('n')
            ->limit(10)
            ->get()
            ->map(fn ($r): array => [(string) $r->nombre, (int) $r->n])
            ->all();
    }

    /**
     * Denuncias georreferenciadas para el mapa de incidencias del tablero.
     * Sólo las que tienen coordenadas capturadas en la toma de conocimiento.
     *
     * @return list<array<string, mixed>>
     */
    private function incidenciasUbicadas(): array
    {
        return Denuncia::query()
            ->whereNotNull('latitud')
            ->where('latitud', '<>', '')
            ->latest('id')
            ->limit(500)
            ->get(['id', 'latitud', 'longitud', 'denuncia', 'con_atencion', 'con_termino'])
            ->map(fn (Denuncia $d): array => [
                'id' => $d->id,
                'lat' => (float) $d->latitud,
                'lng' => (float) $d->longitud,
                'resumen' => Str::limit((string) $d->denuncia, 120),
                'etapa' => $d->estatusLabel(),
            ])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------ Gestión
    public function gestion(): Response
    {
        $total = $this->cuenta([0, 1, 2, 3, 4, 5, 6]);
        $resueltas = $this->cuenta([2, 5, 6]);

        return Inertia::render('tableros/gestion', [
            'kpis' => [
                'capturadas' => $this->cuenta([0]),
                'turnadas' => $this->cuenta([1, 4]),
                'para_resolver' => $this->cuenta([3]),
                'resueltas' => $resueltas,
                'total' => $total,
                'compromisos' => Solicitud::query()
                    ->whereRaw("fecha_comp ~ '^[0-9]{4}-[0-9]{2}-[0-9]{2}'")
                    ->whereRaw('(fecha_comp::date - CURRENT_DATE) BETWEEN 1 AND 10')
                    ->count(),
            ],
            'asociaciones' => $this->resumenGestor(Organizacion::class),
            'ciudadanos' => $this->resumenGestor(Beneficiario::class),
            'cumplimiento' => $total > 0 ? round($resueltas / $total * 100) : 0,
            'porResolver' => $this->porDependencia([3]),
            'atendidasPorDependencia' => $this->porDependencia([5, 6]),
            'porConcepto' => $this->porConcepto([5, 6]),
            'porTipoBeneficiario' => $this->matriz([5, 2, 6], false),
            'topLocalidadCantidad' => $this->topLocalidad([5, 6], false),
            'tablaLocalidades' => $this->tablaLocalidades(),
        ]);
    }

    // --------------------------------------------------------------- Financiero
    public function financiero(): Response
    {
        $total = (float) DB::table('tbl_solicitudes')
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM('.self::MONTO.'), 0) as t')
            ->value('t');

        return Inertia::render('tableros/financiero', [
            'inversionTotal' => $total,
            'inversionPorTipoBeneficiario' => $this->matriz([5, 2, 6], true),
            'topLocalidadInversion' => $this->topLocalidad([5], true),
            'origenInversion' => $this->origenInversion(),
        ]);
    }

    // ----------------------------------------------------------------- Helpers
    /** @param  list<int>  $statuses */
    private function cuenta(array $statuses): int
    {
        return Solicitud::query()->whereIn('status', $statuses)->count();
    }

    private function eventosDe(string $exprFecha): Collection
    {
        return Evento::query()
            ->whereNotNull('inicio')
            ->whereRaw("inicio::date = {$exprFecha}")
            ->orderBy('inicio')
            ->get(['id', 'titulo', 'inicio', 'lugar'])
            ->map(fn (Evento $e): array => [
                'id' => $e->id,
                'titulo' => $e->titulo ?: '(Sin título)',
                'hora' => $e->inicio?->format('H:i'),
                'lugar' => $e->lugar,
            ]);
    }

    /**
     * Conteo del último seguimiento por solicitud, agrupado por dependencia.
     *
     * @param  list<int>  $statuses
     * @return list<array{0: string, 1: int}>
     */
    private function porDependencia(array $statuses): array
    {
        $in = implode(',', $statuses);

        $sub = "(SELECT DISTINCT ON (sg.solicitud_id) sg.dependencia_id
            FROM tbl_seguimientos sg
            JOIN tbl_solicitudes s ON s.id = sg.solicitud_id
            WHERE sg.deleted_at IS NULL AND s.deleted_at IS NULL AND s.status IN ({$in})
            ORDER BY sg.solicitud_id, sg.id DESC) ult";

        return DB::table(DB::raw($sub))
            ->join('cat_dependencias as d', 'd.id', '=', 'ult.dependencia_id')
            ->selectRaw('d.nombre, count(*) as total')
            ->groupBy('d.nombre')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($f): array => [$f->nombre, (int) $f->total])
            ->all();
    }

    /**
     * @param  list<int>  $statuses
     * @return list<array{0: string, 1: int}>
     */
    private function porConcepto(array $statuses): array
    {
        return DB::table('tbl_solicitudes as s')
            ->join('cat_conceptos as c', 'c.id', '=', 's.concepto_id')
            ->whereNull('s.deleted_at')
            ->whereIn('s.status', $statuses)
            ->selectRaw('c.nombre, count(*) as total')
            ->groupBy('c.nombre')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($f): array => [$f->nombre, (int) $f->total])
            ->all();
    }

    /**
     * Matriz tipo de solicitante (Asociación/Ciudadano) × clase de beneficiario
     * (Personal / Comunitaria). Conteo o suma de monto.
     *
     * @param  list<int>  $statuses
     * @return array<string, array{personal: float, comunitaria: float}>
     */
    private function matriz(array $statuses, bool $suma): array
    {
        $metric = $suma ? 'COALESCE(SUM('.self::MONTO.'),0)' : 'COUNT(*)';

        $filas = DB::table('tbl_solicitudes')
            ->whereNull('deleted_at')
            ->whereIn('status', $statuses)
            ->selectRaw("(solicitante_type = ?) as es_org, (bene_final = 3) as es_com, {$metric} as v", [Organizacion::class])
            ->groupByRaw('1, 2')
            ->get();

        $base = ['personal' => 0.0, 'comunitaria' => 0.0];
        $res = ['asociacion' => $base, 'ciudadano' => $base];

        foreach ($filas as $f) {
            $grupo = $f->es_org ? 'asociacion' : 'ciudadano';
            $clase = $f->es_com ? 'comunitaria' : 'personal';
            $res[$grupo][$clase] += (float) $f->v;
        }

        return $res;
    }

    /**
     * @param  list<int>  $statuses
     * @return list<array{0: string, 1: float}>
     */
    private function topLocalidad(array $statuses, bool $suma): array
    {
        $metric = $suma ? 'COALESCE(SUM('.self::MONTO.'),0)' : 'COUNT(*)';

        return DB::table('tbl_solicitudes as s')
            ->join('cat_localidades as l', 'l.id', '=', 's.localidad_resp_id')
            ->whereNull('s.deleted_at')
            ->whereIn('s.status', $statuses)
            ->selectRaw("l.nombre, {$metric} as total")
            ->groupBy('l.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($f): array => [$f->nombre, (float) $f->total])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function resumenGestor(string $tipo): array
    {
        $q = fn (array $statuses = []) => Solicitud::query()
            ->where('solicitante_type', $tipo)
            ->when($statuses !== [], fn ($x) => $x->whereIn('status', $statuses));

        return [
            'gestores' => (int) $q()->distinct()->count('solicitante_id'),
            'total' => $q()->count(),
            'resueltas' => $q([5, 2, 6])->count(),
            'para_resolver' => $q([3])->count(),
            'turnadas' => $q([1, 4])->count(),
            'capturadas' => $q([0])->count(),
        ];
    }

    /**
     * @return list<array{0: string, 1: float}>
     */
    private function origenInversion(): array
    {
        return DB::table('tbl_solicitudes as s')
            ->join('cat_origenes_recurso as o', 'o.id', '=', DB::raw('NULLIF(s.origen, \'\')::int'))
            ->whereNull('s.deleted_at')
            ->where('s.status', 5)
            ->whereRaw("s.origen ~ '^[0-9]+\$'")
            ->selectRaw('o.nombre, count(*) as total')
            ->groupBy('o.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($f): array => [$f->nombre, (float) $f->total])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tablaLocalidades(): array
    {
        $c = fn (string $alias, array $statuses) => 'COUNT(*) FILTER (WHERE s.status IN ('.implode(',', $statuses).')) as '.$alias;

        return DB::table('tbl_solicitudes as s')
            ->join('cat_localidades as l', 'l.id', '=', 's.localidad_resp_id')
            ->whereNull('s.deleted_at')
            ->selectRaw('l.nombre')
            ->selectRaw('count(*) as total')
            ->selectRaw($c('resueltas', [5, 2, 6]))
            ->selectRaw($c('para_resolver', [3]))
            ->selectRaw($c('turnadas', [1, 4]))
            ->selectRaw($c('capturadas', [0]))
            ->selectRaw('COALESCE(SUM('.self::MONTO.'),0) as inversion')
            ->groupBy('l.nombre')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($f): array => [
                'localidad' => $f->nombre,
                'total' => (int) $f->total,
                'resueltas' => (int) $f->resueltas,
                'para_resolver' => (int) $f->para_resolver,
                'turnadas' => (int) $f->turnadas,
                'capturadas' => (int) $f->capturadas,
                'inversion' => (float) $f->inversion,
            ])
            ->all();
    }
}
