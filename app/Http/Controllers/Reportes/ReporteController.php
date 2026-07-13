<?php

namespace App\Http\Controllers\Reportes;

use App\Exports\QueryExport;
use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\CatConcepto;
use App\Models\CatEstado;
use App\Models\CatEstadoCivil;
use App\Models\CatOcupacion;
use App\Models\CatOrigenSolicitud;
use App\Models\CatProfesion;
use App\Models\CatSector;
use App\Models\CatSectorOrganizacion;
use App\Models\CatTipoEvento;
use App\Models\Evento;
use App\Models\Organizacion;
use App\Models\Proveedor;
use App\Models\Solicitud;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteController extends Controller
{
    // ============================================================ AGENDA
    public function agenda(Request $request): Response
    {
        $f = $this->filtrosAgenda($request);
        $eventos = $this->queryAgenda($f)->paginate(20)->withQueryString();

        return Inertia::render('reportes/agenda', [
            'filtros' => $f,
            'opciones' => [
                'tipos' => CatTipoEvento::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
            'filas' => $eventos->getCollection()->map(fn (Evento $e): array => [
                'titulo' => $e->titulo,
                'tipo' => $e->tipoEvento?->nombre,
                'fecha' => $e->inicio?->format('Y-m-d'),
                'hora' => $e->inicio?->format('H:i'),
                'lugar' => $e->lugar,
                'contacto' => $e->contacto,
                'confirmado' => $e->confirmado ? 'Sí' : 'No',
            ])->values(),
            'paginacion' => $this->paginacion($eventos),
        ]);
    }

    public function agendaExcel(Request $request): BinaryFileResponse
    {
        $f = $this->filtrosAgenda($request);

        return Excel::download(new QueryExport(
            fn (): Builder => $this->queryAgenda($f),
            ['Título', 'Tipo', 'Fecha', 'Hora', 'Lugar', 'Contacto', 'Confirmado'],
            fn (Evento $e): array => [
                $e->titulo, $e->tipoEvento?->nombre, $e->inicio?->format('Y-m-d'),
                $e->inicio?->format('H:i'), $e->lugar, $e->contacto, $e->confirmado ? 'Sí' : 'No',
            ],
        ), 'reporte_agenda.xlsx');
    }

    /** @param array<string,mixed> $f */
    private function queryAgenda(array $f): Builder
    {
        return Evento::query()
            ->with('tipoEvento:id,nombre')
            ->when($f['desde'], fn ($q) => $q->whereDate('inicio', '>=', $f['desde']))
            ->when($f['hasta'], fn ($q) => $q->whereDate('inicio', '<=', $f['hasta']))
            ->when($f['tipo_evento_id'], fn ($q) => $q->where('tipo_evento_id', $f['tipo_evento_id']))
            ->when($f['confirmado'] !== null, fn ($q) => $q->where('confirmado', $f['confirmado']))
            ->when($f['discurso'] !== null, fn ($q) => $q->where('discurso', $f['discurso']))
            ->when($f['privado'] !== null, fn ($q) => $q->where('privado', $f['privado']))
            ->orderBy('inicio');
    }

    /** @return array<string,mixed> */
    private function filtrosAgenda(Request $request): array
    {
        return [
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
            'tipo_evento_id' => $request->integer('tipo_evento_id') ?: null,
            'confirmado' => $this->tri($request, 'confirmado'),
            'discurso' => $this->tri($request, 'discurso'),
            'privado' => $this->tri($request, 'privado'),
        ];
    }

    // ========================================================= DIRECTORIO
    public function directorio(Request $request): Response
    {
        $f = $this->filtrosDirectorio($request);
        $registros = $this->queryDirectorio($f)->paginate(20)->withQueryString();

        return Inertia::render('reportes/directorio', [
            'filtros' => $f,
            'opciones' => [
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
                'sectores' => CatSector::query()->orderBy('nombre')->get(['id', 'nombre']),
                'sectoresOrg' => CatSectorOrganizacion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'ocupaciones' => CatOcupacion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'profesiones' => CatProfesion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estadosCiviles' => CatEstadoCivil::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
            'filas' => $registros->getCollection()->map(fn ($r): array => $this->filaDirectorio($f['tipo'], $r))->values(),
            'paginacion' => $this->paginacion($registros),
        ]);
    }

    public function directorioExcel(Request $request): BinaryFileResponse
    {
        $f = $this->filtrosDirectorio($request);
        [$headings, ] = $this->columnasDirectorio($f['tipo']);

        return Excel::download(new QueryExport(
            fn (): Builder => $this->queryDirectorio($f),
            $headings,
            fn ($r): array => array_values($this->filaDirectorio($f['tipo'], $r)),
        ), 'reporte_directorio.xlsx');
    }

    /** @param array<string,mixed> $f */
    private function queryDirectorio(array $f): Builder
    {
        $con = fn ($q) => $q
            ->when($f['estado_id'], fn ($x) => $x->where('estado_id', $f['estado_id']))
            ->when($f['municipio_id'], fn ($x) => $x->where('municipio_id', $f['municipio_id']))
            ->when($f['localidad_id'], fn ($x) => $x->where('localidad_id', $f['localidad_id']));

        if ($f['tipo'] === 'asociaciones') {
            return $con(Organizacion::query()
                ->with(['estado:id,nombre', 'municipio:id,nombre', 'sectorOrganizacion:id,nombre', 'representante:id,nombre,paterno,materno']))
                ->when($f['sector_id'], fn ($q) => $q->where('sector_organizacion_id', $f['sector_id']))
                ->orderBy('nombre');
        }

        if ($f['tipo'] === 'proveedores') {
            return $con(Proveedor::query()
                ->with(['estado:id,nombre', 'municipio:id,nombre']))
                ->orderBy('nombre');
        }

        return $con(Beneficiario::query()
            ->with(['estado:id,nombre', 'municipio:id,nombre', 'localidad:id,nombre', 'sector:id,nombre', 'ocupacion:id,nombre', 'profesion:id,nombre']))
            ->when($f['genero'], fn ($q) => $q->where('genero', $f['genero']))
            ->when($f['sector_id'], fn ($q) => $q->where('sector_id', $f['sector_id']))
            ->when($f['ocupacion_id'], fn ($q) => $q->where('ocupacion_id', $f['ocupacion_id']))
            ->when($f['profesion_id'], fn ($q) => $q->where('profesion_id', $f['profesion_id']))
            ->when($f['estado_civil_id'], fn ($q) => $q->where('estado_civil_id', $f['estado_civil_id']))
            ->orderBy('paterno');
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function columnasDirectorio(string $tipo): array
    {
        return match ($tipo) {
            'asociaciones' => [['Nombre', 'Tipo de organización', 'Representante', 'Celular', 'Teléfono', 'Correo', 'Municipio', 'Estado'], []],
            'proveedores' => [['Nombre', 'RFC', 'Especialidad', 'Celular', 'Teléfono', 'Correo', 'Municipio', 'Estado'], []],
            default => [['Nombre', 'CURP', 'Género', 'Celular', 'Correo', 'Ocupación', 'Sector', 'Localidad', 'Municipio', 'Estado'], []],
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function filaDirectorio(string $tipo, $r): array
    {
        if ($tipo === 'asociaciones') {
            return [
                'nombre' => $r->nombre,
                'tipo' => $r->sectorOrganizacion?->nombre,
                'representante' => $r->representante?->nombreCompleto(),
                'celular' => $r->celular,
                'telefono' => $r->telefono,
                'correo' => $r->correo,
                'municipio' => $r->municipio?->nombre,
                'estado' => $r->estado?->nombre,
            ];
        }

        if ($tipo === 'proveedores') {
            return [
                'nombre' => $r->nombre,
                'rfc' => $r->rfc,
                'especialidad' => $r->especialidad,
                'celular' => $r->celular,
                'telefono' => $r->telefono,
                'correo' => $r->correo,
                'municipio' => $r->municipio?->nombre,
                'estado' => $r->estado?->nombre,
            ];
        }

        return [
            'nombre' => $r->nombreCompleto(),
            'curp' => $r->curp,
            'genero' => match ($r->genero) { 1 => 'Masculino', 2 => 'Femenino', default => '' },
            'celular' => $r->celular,
            'correo' => $r->correo,
            'ocupacion' => $r->ocupacion?->nombre,
            'sector' => $r->sector?->nombre,
            'localidad' => $r->localidad?->nombre,
            'municipio' => $r->municipio?->nombre,
            'estado' => $r->estado?->nombre,
        ];
    }

    /** @return array<string,mixed> */
    private function filtrosDirectorio(Request $request): array
    {
        $tipo = $request->string('tipo')->toString();
        $tipo = in_array($tipo, ['ciudadanos', 'asociaciones', 'proveedores'], true) ? $tipo : 'ciudadanos';

        return [
            'tipo' => $tipo,
            'estado_id' => $request->integer('estado_id') ?: null,
            'municipio_id' => $request->integer('municipio_id') ?: null,
            'localidad_id' => $request->integer('localidad_id') ?: null,
            'genero' => $request->integer('genero') ?: null,
            'sector_id' => $request->integer('sector_id') ?: null,
            'ocupacion_id' => $request->integer('ocupacion_id') ?: null,
            'profesion_id' => $request->integer('profesion_id') ?: null,
            'estado_civil_id' => $request->integer('estado_civil_id') ?: null,
        ];
    }

    // ============================================================ GESTIÓN
    public function gestion(Request $request): Response
    {
        $f = $this->filtrosGestion($request);
        $solicitudes = $this->queryGestion($f)->paginate(20)->withQueryString();

        return Inertia::render('reportes/gestion', [
            'filtros' => $f,
            'opciones' => [
                'estatus' => collect(Solicitud::ESTATUS)->map(fn (string $n, int $id) => ['id' => $id, 'nombre' => $n])->values(),
                'conceptos' => CatConcepto::query()->orderBy('nombre')->get(['id', 'nombre']),
                'procedencias' => CatOrigenSolicitud::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
            'filas' => $solicitudes->getCollection()->map(fn (Solicitud $s): array => $this->filaGestion($s))->values(),
            'paginacion' => $this->paginacion($solicitudes),
        ]);
    }

    public function gestionExcel(Request $request): BinaryFileResponse
    {
        $f = $this->filtrosGestion($request);

        return Excel::download(new QueryExport(
            fn (): Builder => $this->queryGestion($f),
            ['Folio', 'Solicitante', 'Solicitud', 'Estatus', 'Concepto', 'Localidad', 'Fecha recepción', 'Monto'],
            fn (Solicitud $s): array => array_values($this->filaGestion($s)),
        ), 'reporte_gestion.xlsx');
    }

    /** @param array<string,mixed> $f */
    private function queryGestion(array $f): Builder
    {
        return Solicitud::query()
            ->with(['solicitante', 'concepto:id,nombre', 'localidadResp:id,nombre'])
            ->when($f['desde'], fn ($q) => $q->whereDate('fecha_recepcion', '>=', $f['desde']))
            ->when($f['hasta'], fn ($q) => $q->whereDate('fecha_recepcion', '<=', $f['hasta']))
            ->when($f['estatus'] !== [], fn ($q) => $q->whereIn('status', $f['estatus']))
            ->when($f['concepto_id'], fn ($q) => $q->where('concepto_id', $f['concepto_id']))
            ->when($f['procedencia_id'], fn ($q) => $q->where('procedencia_id', $f['procedencia_id']))
            ->when($f['control_administrativo'] !== null, fn ($q) => $q->where('control_administrativo', $f['control_administrativo']))
            ->when($f['localidad_resp_id'], fn ($q) => $q->where('localidad_resp_id', $f['localidad_resp_id']))
            ->when($f['monto_min'] !== null, fn ($q) => $q
                ->whereRaw("monto ~ '^[0-9]+(\\.[0-9]+)?\$'")
                ->whereRaw('monto::numeric >= ?', [$f['monto_min']]))
            ->when($f['monto_max'] !== null, fn ($q) => $q
                ->whereRaw("monto ~ '^[0-9]+(\\.[0-9]+)?\$'")
                ->whereRaw('monto::numeric <= ?', [$f['monto_max']]))
            ->orderByDesc('fecha_recepcion');
    }

    /**
     * @return array<string,mixed>
     */
    private function filaGestion(Solicitud $s): array
    {
        $solicitante = $s->solicitante instanceof Beneficiario
            ? $s->solicitante->nombreCompleto()
            : $s->solicitante?->nombre;

        return [
            'folio' => $s->folio ?: $s->folio_sistema,
            'solicitante' => $solicitante,
            'solicitud' => $s->solicitud,
            'estatus' => $s->estatusLabel(),
            'concepto' => $s->concepto?->nombre,
            'localidad' => $s->localidadResp?->nombre,
            'fecha_recepcion' => $s->fecha_recepcion?->toDateString(),
            'monto' => $s->monto,
        ];
    }

    /** @return array<string,mixed> */
    private function filtrosGestion(Request $request): array
    {
        $estatusRaw = $request->input('estatus', []);
        $estatusArr = is_array($estatusRaw) ? $estatusRaw : explode(',', (string) $estatusRaw);

        return [
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
            'estatus' => array_values(array_filter(
                array_map('intval', $estatusArr),
                fn ($v) => $v >= 0 && $v <= 6,
            )),
            'concepto_id' => $request->integer('concepto_id') ?: null,
            'procedencia_id' => $request->integer('procedencia_id') ?: null,
            'control_administrativo' => $this->tri($request, 'control_administrativo'),
            'localidad_resp_id' => $request->integer('localidad_resp_id') ?: null,
            'monto_min' => $request->filled('monto_min') ? (float) $request->input('monto_min') : null,
            'monto_max' => $request->filled('monto_max') ? (float) $request->input('monto_max') : null,
        ];
    }

    // ============================================================= Utilidades
    /** Lee un filtro booleano de 3 estados: null (todos) / true / false. */
    private function tri(Request $request, string $campo): ?bool
    {
        $v = $request->input($campo);

        return ($v === null || $v === '') ? null : (bool) (int) $v;
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $p
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
