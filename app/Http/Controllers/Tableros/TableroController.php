<?php

namespace App\Http\Controllers\Tableros;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Evento;
use App\Models\Organizacion;
use App\Models\Solicitud;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
