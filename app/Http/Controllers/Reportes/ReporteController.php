<?php

namespace App\Http\Controllers\Reportes;

use App\Exports\ColeccionExport;
use App\Exports\QueryExport;
use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\CatConcepto;
use App\Models\CatEstado;
use App\Models\CatEstadoCivil;
use App\Models\CatNivelViolencia;
use App\Models\CatOcupacion;
use App\Models\CatOrigenDenuncia;
use App\Models\CatOrigenSolicitud;
use App\Models\CatProfesion;
use App\Models\CatSector;
use App\Models\CatSectorOrganizacion;
use App\Models\CatSegSector;
use App\Models\CatTipoEvento;
use App\Models\CatTipoIncidencia;
use App\Models\Denuncia;
use App\Models\Detenido;
use App\Models\Evento;
use App\Models\Obra;
use App\Models\Organizacion;
use App\Models\Proveedor;
use App\Models\Solicitud;
use App\Models\Verificacion;
use App\Support\CamposObra;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteController extends Controller
{
    // ============================================================ AGENDA
    /**
     * Columnas elegibles, como las casillas de `reportes/agenda.php`.
     * La clave viaja en la URL; el orden de este arreglo es el orden en que
     * salen en la tabla y en el Excel.
     *
     * @var array<string, string>
     */
    public const COLUMNAS_AGENDA = [
        'titulo' => 'Título del evento',
        'tipo' => 'Tipo',
        'descripcion' => 'Descripción',
        'contacto' => 'Contacto / Teléfono',
        'fecha' => 'Fecha',
        'hora' => 'Hora',
        'lugar' => 'Lugar del evento',
        'localidad' => 'Localidad, Colonia, Barrio, Fracc., etc.',
        'asiste' => '¿Quién asiste?',
        'agregado' => 'Agregado por',
        'clasificacion' => 'Clasificación',
    ];

    private const COLUMNAS_AGENDA_POR_OMISION = [
        'titulo', 'tipo', 'fecha', 'hora', 'lugar', 'contacto',
    ];

    public function agenda(Request $request): Response
    {
        $f = $this->filtrosAgenda($request);
        $eventos = $this->queryAgenda($f)->paginate(20)->withQueryString();

        return Inertia::render('reportes/agenda', [
            'filtros' => $f,
            'opciones' => [
                'tipos' => CatTipoEvento::query()->orderBy('nombre')->get(['id', 'nombre']),
                'columnas' => $this->catalogo(self::COLUMNAS_AGENDA),
            ],
            'filas' => $eventos->getCollection()
                ->map(fn (Evento $e): array => $this->filaAgenda($e, $f['columnas']))
                ->values(),
            'paginacion' => $this->paginacion($eventos),
        ]);
    }

    public function agendaExcel(Request $request): BinaryFileResponse
    {
        $f = $this->filtrosAgenda($request);

        return Excel::download(new QueryExport(
            fn (): Builder => $this->queryAgenda($f),
            array_map(fn (string $c): string => self::COLUMNAS_AGENDA[$c], $f['columnas']),
            fn (Evento $e): array => array_values($this->filaAgenda($e, $f['columnas'])),
        ), 'reporte_agenda.xlsx');
    }

    /** @param array<string,mixed> $f */
    private function queryAgenda(array $f): Builder
    {
        return Evento::query()
            ->with(['tipoEvento:id,nombre', 'localidad:id,nombre', 'creadoPor:id,name'])
            ->when($f['desde'], fn ($q) => $q->whereDate('inicio', '>=', $f['desde']))
            ->when($f['hasta'], fn ($q) => $q->whereDate('inicio', '<=', $f['hasta']))
            ->when($f['tipo_evento_id'], fn ($q) => $q->where('tipo_evento_id', $f['tipo_evento_id']))
            ->when($f['confirmado'] !== null, fn ($q) => $q->where('confirmado', $f['confirmado']))
            ->when($f['discurso'] !== null, fn ($q) => $q->where('discurso', $f['discurso']))
            ->when($f['privado'] !== null, fn ($q) => $q->where('privado', $f['privado']))
            ->orderBy('inicio');
    }

    /**
     * @param  list<string>  $columnas
     * @return array<string,mixed>
     */
    private function filaAgenda(Evento $e, array $columnas): array
    {
        $hora = $e->inicio?->format('H:i');
        if ($hora !== null && $e->fin !== null) {
            $hora .= ' - '.$e->fin->format('H:i');
        }

        $todas = [
            'titulo' => $e->titulo,
            'tipo' => $e->tipoEvento?->nombre,
            'descripcion' => $e->descripcion,
            'contacto' => $this->unir(' / ', [$e->contacto, $e->telefono]),
            'fecha' => $e->inicio?->format('Y-m-d'),
            'hora' => $hora,
            'lugar' => $e->lugar,
            'localidad' => $e->localidad?->nombre,
            // El legacy sólo distingue al presidente del representante.
            'asiste' => $e->asiste === 1
                ? 'PRESIDENTE MUNICIPAL'
                : ($e->representante ? 'REPRESENTANTE: '.$e->representante : null),
            'agregado' => $e->creadoPor?->name,
            'clasificacion' => $e->privado ? 'PRIVADO' : 'PÚBLICO',
        ];

        return array_intersect_key($todas, array_flip($columnas));
    }

    /** @return array<string,mixed> */
    private function filtrosAgenda(Request $request): array
    {
        return [
            ...$this->rangoFechas($request),
            'tipo_evento_id' => $request->integer('tipo_evento_id') ?: null,
            'confirmado' => $this->tri($request, 'confirmado'),
            'discurso' => $this->tri($request, 'discurso'),
            'privado' => $this->tri($request, 'privado'),
            'columnas' => $this->columnasPedidas(
                $request,
                self::COLUMNAS_AGENDA,
                self::COLUMNAS_AGENDA_POR_OMISION,
            ),
        ];
    }

    // ========================================================= DIRECTORIO
    /**
     * Un catálogo por pestaña, como los tres juegos de casillas de
     * `reportes/directorio.php`.
     *
     * @var array<string, array<string, string>>
     */
    public const COLUMNAS_DIRECTORIO = [
        'ciudadanos' => [
            'nombre' => 'Nombre',
            'alias' => 'Alias',
            'sexo' => 'Sexo',
            'empresa' => 'Empresa',
            'puesto' => 'Puesto',
            'edad_nac' => 'Edad / Nacimiento',
            'curp' => 'CURP',
            'edo_civil' => 'Estado civil',
            'celular_princ' => 'Teléfono celular principal',
            'celular_secu' => 'Teléfono celular secundario',
            'tel_trabajo' => 'Teléfono del trabajo',
            'tel_particular' => 'Teléfono particular',
            'email_princ' => 'Correo electrónico principal',
            'email_secu' => 'Correo electrónico secundario',
            'asistente' => 'Asistente / Teléfono / Correo electrónico',
            'conyuge' => 'Cónyuge / Teléfono / Nacimiento',
            'facebook' => 'Facebook',
            'twitter' => 'Twitter',
            'direccion' => 'Dirección',
            'localidad' => 'Localidad',
            'municipio' => 'Municipio',
            'estado' => 'Estado',
            'sector' => 'Sector',
            'seccion' => 'Sección electoral',
            'filiacion' => 'Filiación',
            'antagonico' => 'Antagónico',
            'lider' => 'Líder',
            'influencia' => 'Grado de influencia',
        ],
        'asociaciones' => [
            'tipo' => 'Tipo',
            'representante' => 'Representante',
            'nombre' => 'Nombre',
            'sector' => 'Sector',
            'celular' => 'Celular',
            'telefono' => 'Teléfono fijo',
            'correo' => 'Correo electrónico',
            'direccion' => 'Dirección',
            'localidad' => 'Localidad',
            'municipio' => 'Municipio',
            'estado' => 'Estado',
        ],
        'proveedores' => [
            'tipo' => 'Tipo',
            'nombre' => 'Nombre',
            'rfc' => 'R.F.C.',
            'representante' => 'Representante',
            'celular' => 'Celular',
            'telefono' => 'Teléfono fijo',
            'correo' => 'Correo electrónico',
            'padron' => 'Padrón de proveedores / contratistas',
            'especialidad' => 'Especialidad',
            'direccion' => 'Dirección',
            'localidad' => 'Localidad',
            'municipio' => 'Municipio',
            'estado' => 'Estado',
        ],
    ];

    /** @var array<string, list<string>> */
    private const COLUMNAS_DIRECTORIO_POR_OMISION = [
        'ciudadanos' => ['nombre', 'sexo', 'curp', 'celular_princ', 'email_princ', 'localidad', 'municipio', 'estado', 'sector'],
        'asociaciones' => ['tipo', 'representante', 'nombre', 'celular', 'telefono', 'correo', 'municipio', 'estado'],
        'proveedores' => ['nombre', 'rfc', 'representante', 'celular', 'telefono', 'correo', 'especialidad', 'municipio', 'estado'],
    ];

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
                // Sólo el catálogo de la pestaña activa: las columnas de
                // ciudadanos no aplican a proveedores.
                'columnas' => $this->catalogo(self::COLUMNAS_DIRECTORIO[$f['tipo']]),
            ],
            'filas' => $registros->getCollection()
                ->map(fn ($r): array => $this->filaDirectorio($f['tipo'], $r, $f['columnas']))
                ->values(),
            'paginacion' => $this->paginacion($registros),
        ]);
    }

    public function directorioExcel(Request $request): BinaryFileResponse
    {
        $f = $this->filtrosDirectorio($request);
        $catalogo = self::COLUMNAS_DIRECTORIO[$f['tipo']];

        return Excel::download(new QueryExport(
            fn (): Builder => $this->queryDirectorio($f),
            array_map(fn (string $c): string => $catalogo[$c], $f['columnas']),
            fn ($r): array => array_values($this->filaDirectorio($f['tipo'], $r, $f['columnas'])),
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
            return Organizacion::query()
                ->with(['sectorOrganizacion:id,nombre', 'representante', 'estado:id,nombre', 'municipio:id,nombre', 'localidad:id,nombre'])
                ->tap($con)
                ->when($f['sector_id'], fn ($q) => $q->where('sector_organizacion_id', $f['sector_id']))
                ->orderBy('nombre');
        }

        if ($f['tipo'] === 'proveedores') {
            return Proveedor::query()
                ->with(['representante', 'estado:id,nombre', 'municipio:id,nombre', 'localidad:id,nombre'])
                ->tap($con)
                ->orderBy('nombre');
        }

        return Beneficiario::query()
            ->with([
                'estado:id,nombre', 'municipio:id,nombre', 'localidad:id,nombre',
                'ocupacion:id,nombre', 'sector:id,nombre', 'estadoCivil:id,nombre',
                'filiacion:id,nombre', 'antagonico:id,nombre', 'lider:id,nombre', 'influencia:id,nombre',
            ])
            ->tap($con)
            ->when($f['genero'], fn ($q) => $q->where('genero', $f['genero']))
            ->when($f['sector_id'], fn ($q) => $q->where('sector_id', $f['sector_id']))
            ->when($f['ocupacion_id'], fn ($q) => $q->where('ocupacion_id', $f['ocupacion_id']))
            ->when($f['profesion_id'], fn ($q) => $q->where('profesion_id', $f['profesion_id']))
            ->when($f['estado_civil_id'], fn ($q) => $q->where('estado_civil_id', $f['estado_civil_id']))
            ->orderBy('paterno');
    }

    /**
     * @param  list<string>  $columnas
     * @return array<string,mixed>
     */
    private function filaDirectorio(string $tipo, $r, array $columnas): array
    {
        if ($tipo === 'asociaciones') {
            $todas = [
                'tipo' => match ($r->tipo) {
                    1 => 'ASOCIACIÓN',
                    2 => 'GRUPO ORGANIZADO',
                    default => null,
                },
                'representante' => $r->representante?->nombreCompleto(),
                'nombre' => $r->nombre,
                'sector' => $r->sectorOrganizacion?->nombre,
                'celular' => $r->celular,
                'telefono' => $r->telefono,
                'correo' => $r->correo,
                'direccion' => $this->direccion($r),
                'localidad' => $r->localidad?->nombre,
                'municipio' => $r->municipio?->nombre,
                'estado' => $r->estado?->nombre,
            ];
        } elseif ($tipo === 'proveedores') {
            $todas = [
                // En el legacy 0 era "PROVEEDOR"; al migrar el 0 se volvió nulo.
                'tipo' => match ($r->tipo) {
                    null => 'PROVEEDOR',
                    1 => 'GRUPO ORGANIZADO',
                    default => 'SIN ESPECIFICAR',
                },
                'nombre' => $r->nombre,
                'rfc' => $r->rfc,
                'representante' => $r->representante?->nombreCompleto() ?: $r->rep_legal,
                'celular' => $r->celular,
                'telefono' => $r->telefono,
                'correo' => $r->correo,
                'padron' => $r->num_prov_gob,
                'especialidad' => $r->especialidad,
                'direccion' => $this->direccion($r),
                'localidad' => $r->localidad?->nombre,
                'municipio' => $r->municipio?->nombre,
                'estado' => $r->estado?->nombre,
            ];
        } else {
            $todas = [
                'nombre' => $r->nombreCompleto(),
                'alias' => $r->alias,
                'sexo' => match ($r->genero) {
                    1 => 'Masculino',
                    2 => 'Femenino',
                    default => null,
                },
                'empresa' => $r->empresa,
                'puesto' => $r->puesto,
                'edad_nac' => $this->edadYNacimiento($r->nacimiento),
                'curp' => $r->curp,
                'edo_civil' => $r->estadoCivil?->nombre,
                'celular_princ' => $r->celular,
                'celular_secu' => $r->celular2,
                'tel_trabajo' => $r->tel_empresa,
                'tel_particular' => $r->telefono,
                'email_princ' => $r->correo,
                'email_secu' => $r->correo2,
                'asistente' => $this->unir(' / ', [$r->asist_nombre, $r->asist_movil, $r->asist_correo]),
                'conyuge' => $this->unir(' / ', [$r->conyuge_nombre, $r->conyuge_movil, $r->conyuge_nacimiento]),
                'facebook' => $r->facebook,
                'twitter' => $r->twitter,
                'direccion' => $this->direccion($r),
                'localidad' => $r->localidad?->nombre,
                'municipio' => $r->municipio?->nombre,
                'estado' => $r->estado?->nombre,
                'sector' => $r->sector?->nombre,
                'seccion' => $r->seccion,
                'filiacion' => $r->filiacion?->nombre,
                'antagonico' => $r->antagonico?->nombre,
                'lider' => $r->lider?->nombre,
                'influencia' => $r->influencia?->nombre,
            ];
        }

        return array_intersect_key($todas, array_flip($columnas));
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
            'columnas' => $this->columnasPedidas(
                $request,
                self::COLUMNAS_DIRECTORIO[$tipo],
                self::COLUMNAS_DIRECTORIO_POR_OMISION[$tipo],
            ),
        ];
    }

    // ============================================================ GESTIÓN
    /**
     * Columnas de la pestaña DETALLE de `reportes/gestion.php`.
     *
     * @var array<string, string>
     */
    public const COLUMNAS_GESTION = [
        'solicitante' => 'Solicitante',
        'direccion' => 'Dirección',
        'foliosolicitud' => 'Folio de la solicitud',
        'solicitud' => 'Solicitud',
        'estatus' => 'Estatus',
        'localidad' => 'Localidad, Colonia, Barrio, Fracc., etc.',
        'recepcionsol' => 'Fecha de recepción de la solicitud',
        'origen' => 'Origen de la solicitud',
        'apoyo' => 'Apoyo',
        'cantidad' => 'Cantidad',
        'monto' => 'Monto de la inversión',
        'beneficiarios' => 'Número de beneficiarios',
        'rubros' => 'Rubros',
        'sectores' => 'Segmentos poblacionales',
        'concepto' => 'Concepto',
        'tipoben' => 'Tipo de beneficiario',
        'entrega' => 'Fecha de entrega de apoyo',
        'respuesta' => 'Instrucción y/o Respuesta a la solicitud',
        'dpndnc' => 'Dependencia',
        'area' => 'Área / Dirección',
        'vrfcr' => 'Verificar',
        'tp_sol' => 'Tipo de solicitud',
    ];

    private const COLUMNAS_GESTION_POR_OMISION = [
        'solicitante', 'foliosolicitud', 'solicitud', 'estatus', 'localidad',
        'recepcionsol', 'monto', 'concepto',
    ];

    /**
     * Las cuatro pestañas de `reportes/gestion.php`. Cada una responde una
     * pregunta distinta y tiene sus propias columnas.
     *
     * @var array<string, string>
     */
    public const VISTAS_GESTION = [
        'general' => 'General',
        'detalle' => 'Detalle',
        'historico' => 'Histórico de atendidas / no aprobadas',
        'atendidas' => 'Atendidas',
    ];

    /** Resumen por localidad: una fila por localidad con sus contadores. */
    public const COLUMNAS_GESTION_GENERAL = [
        'localidad' => 'Localidad, Colonia, Barrio, Fracc., etc.',
        'solicitantes' => 'Número de solicitantes',
        'monto' => 'Monto de inversión',
        'beneficiarios' => 'Número de beneficiarios',
        'total' => 'Total de solicitudes',
        'capturadas' => 'Solicitudes capturadas',
        'turnadas' => 'Solicitudes turnadas',
        'no_aprobadas' => 'Solicitudes no aprobadas',
        'resolver' => 'Solicitudes para resolver',
        'respuesta' => 'Solicitudes con respuesta del área',
        'atendidas' => 'Solicitudes atendidas',
        'rapidas' => 'Solicitudes con atención rápida',
        'obra' => 'Solicitudes convertidas en obra',
    ];

    private const COLUMNAS_GESTION_GENERAL_POR_OMISION = [
        'localidad', 'solicitantes', 'total', 'atendidas', 'resolver', 'monto',
    ];

    /** Histórico y Atendidas traen columnas fijas en el legacy. */
    public const COLUMNAS_GESTION_HISTORICO = [
        'localidad' => 'Localidad',
        'solicitante' => 'Solicitante',
        'beneficiarios' => 'Beneficiarios',
        'area_captura' => 'Área que captura',
        'solicitud' => 'Solicitud',
        'recepcion' => 'Fecha de recepción',
    ];

    public const COLUMNAS_GESTION_ATENDIDAS = [
        'localidad' => 'Localidad',
        'solicitante' => 'Solicitante',
        'beneficiarios' => 'Beneficiarios',
        'area_captura' => 'Área que captura',
        'solicitud' => 'Solicitud',
        'recepcion' => 'Fecha de recepción',
        'fecha_atencion' => 'Fecha atención',
        'area' => 'Área',
        'estatus' => 'Estatus',
        'registro' => 'Fecha registro en plataforma',
        'respuesta' => 'Respuesta',
    ];

    public function gestion(Request $request): Response
    {
        $f = $this->filtrosGestion($request);

        [$filas, $paginacion] = $f['vista'] === 'general'
            ? $this->gestionGeneral($f)
            : $this->gestionListado($f);

        return Inertia::render('reportes/gestion', [
            'filtros' => $f,
            'opciones' => [
                'estatus' => collect(Solicitud::ESTATUS)->map(fn (string $n, int $id) => ['id' => $id, 'nombre' => $n])->values(),
                'conceptos' => CatConcepto::query()->orderBy('nombre')->get(['id', 'nombre']),
                'procedencias' => CatOrigenSolicitud::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
                'vistas' => collect(self::VISTAS_GESTION)
                    ->map(fn (string $label, string $clave) => ['clave' => $clave, 'label' => $label])
                    ->values(),
                'columnas' => $this->catalogo($this->catalogoGestion($f['vista'])),
                // Sólo General y Detalle traen casillas en el legacy.
                'eligeColumnas' => in_array($f['vista'], ['general', 'detalle'], true),
            ],
            'filas' => $filas,
            'paginacion' => $paginacion,
        ]);
    }

    /** @return array<string,string> */
    private function catalogoGestion(string $vista): array
    {
        return match ($vista) {
            'general' => self::COLUMNAS_GESTION_GENERAL,
            'historico' => self::COLUMNAS_GESTION_HISTORICO,
            'atendidas' => self::COLUMNAS_GESTION_ATENDIDAS,
            default => self::COLUMNAS_GESTION,
        };
    }

    /**
     * Vista General: una fila por localidad. Se agrega en la base porque son
     * ~47 mil solicitudes; el monto es texto en el legacy, así que se limpia
     * antes de sumar.
     *
     * @param  array<string,mixed>  $f
     * @return array{0: mixed, 1: array<string,mixed>}
     */
    private function gestionGeneral(array $f): array
    {
        $num = fn (string $col) => "sum(CASE WHEN {$col} ~ '^[0-9]+(\\.[0-9]+)?\$' THEN {$col}::numeric ELSE 0 END)";
        $cuenta = fn (string $cond) => "count(*) filter (where {$cond})";

        $filas = $this->queryGestion($f)
            // Sin `reorder` quedaría el ORDER BY por fecha, que no está en el
            // GROUP BY y Postgres rechaza.
            ->reorder()
            ->getQuery()
            ->leftJoin('cat_localidades as l', 'l.id', '=', 'tbl_solicitudes.localidad_resp_id')
            ->groupBy('l.nombre')
            ->selectRaw(implode(', ', [
                "coalesce(l.nombre, 'Sin localidad') as localidad",
                'count(distinct (solicitante_type, solicitante_id)) as solicitantes',
                $num('monto').' as monto',
                $num('num_bene').' as beneficiarios',
                'count(*) as total',
                $cuenta('status = 0').' as capturadas',
                $cuenta('status = 1').' as turnadas',
                $cuenta('status = 2').' as no_aprobadas',
                $cuenta('status = 3').' as resolver',
                $cuenta('status = 4').' as respuesta',
                $cuenta('status = 5').' as atendidas',
                $cuenta('status = 6').' as rapidas',
                $cuenta('convertida_obra in (1, 2)').' as obra',
            ]))
            ->orderByDesc('total')
            ->paginate(20)
            ->withQueryString();

        return [
            $filas->getCollection()
                ->map(fn ($r): array => array_intersect_key(
                    [
                        'localidad' => $r->localidad,
                        'solicitantes' => (int) $r->solicitantes,
                        'monto' => (float) $r->monto,
                        'beneficiarios' => (int) $r->beneficiarios,
                        'total' => (int) $r->total,
                        'capturadas' => (int) $r->capturadas,
                        'turnadas' => (int) $r->turnadas,
                        'no_aprobadas' => (int) $r->no_aprobadas,
                        'resolver' => (int) $r->resolver,
                        'respuesta' => (int) $r->respuesta,
                        'atendidas' => (int) $r->atendidas,
                        'rapidas' => (int) $r->rapidas,
                        'obra' => (int) $r->obra,
                    ],
                    array_flip($f['columnas']),
                ))
                ->values(),
            $this->paginacion($filas),
        ];
    }

    /**
     * Detalle, Histórico y Atendidas: listado de solicitudes.
     *
     * @param  array<string,mixed>  $f
     * @return array{0: mixed, 1: array<string,mixed>}
     */
    private function gestionListado(array $f): array
    {
        $solicitudes = $this->queryGestion($f)->paginate(20)->withQueryString();

        return [
            $solicitudes->getCollection()
                ->map(fn (Solicitud $s): array => $f['vista'] === 'detalle'
                    ? $this->filaGestion($s, $f['columnas'])
                    : $this->filaGestionCerrada($s, $f['columnas']))
                ->values(),
            $this->paginacion($solicitudes),
        ];
    }

    /**
     * Fila de Histórico y de Atendidas. Comparten las primeras columnas; la
     * de Atendidas añade cómo y cuándo se cerró.
     *
     * @param  list<string>  $columnas
     * @return array<string,mixed>
     */
    private function filaGestionCerrada(Solicitud $s, array $columnas): array
    {
        $ultimo = $s->seguimientos->sortByDesc('id')->first();

        $solicitante = $s->solicitante instanceof Beneficiario
            ? $s->solicitante->nombreCompleto()
            : $s->solicitante?->nombre;

        $todas = [
            'localidad' => $s->localidadResp?->nombre,
            'solicitante' => $solicitante,
            'beneficiarios' => $s->desc_bene ?: $s->num_bene,
            'area_captura' => $s->procedencia?->nombre,
            'solicitud' => $s->solicitud,
            'recepcion' => $s->fecha_recepcion?->toDateString(),
            'fecha_atencion' => $s->fecha_resp,
            'area' => $ultimo?->area?->nombre ?? $ultimo?->dependencia?->nombre,
            'estatus' => $s->estatusLabel(),
            // El legacy toma la fecha de creación del seguimiento cuando la
            // solicitud se rechazó, y la de respuesta cuando se atendió.
            'registro' => $s->status === 2
                ? $ultimo?->created_at?->toDateTimeString()
                : $ultimo?->fecha_respuesta,
            'respuesta' => $ultimo?->respuesta,
        ];

        return array_intersect_key($todas, array_flip($columnas));
    }

    public function gestionExcel(Request $request): BinaryFileResponse
    {
        $f = $this->filtrosGestion($request);

        $catalogo = $this->catalogoGestion($f['vista']);
        $encabezados = array_map(fn (string $c): string => $catalogo[$c], $f['columnas']);

        // La vista General ya viene agregada por localidad: se exporta lo
        // mismo que se ve, no la consulta cruda.
        if ($f['vista'] === 'general') {
            [$filas] = $this->gestionGeneral($f);

            return Excel::download(
                new ColeccionExport($encabezados, $filas->map(fn ($fila) => array_values($fila))->all()),
                'reporte_gestion.xlsx',
            );
        }

        return Excel::download(new QueryExport(
            fn (): Builder => $this->queryGestion($f),
            $encabezados,
            fn (Solicitud $s): array => array_values($f['vista'] === 'detalle'
                ? $this->filaGestion($s, $f['columnas'])
                : $this->filaGestionCerrada($s, $f['columnas'])),
        ), 'reporte_gestion.xlsx');
    }

    /** @param array<string,mixed> $f */
    private function queryGestion(array $f): Builder
    {
        return Solicitud::query()
            ->with([
                'solicitante', 'concepto:id,nombre', 'localidadResp:id,nombre',
                'procedencia:id,nombre', 'rubros:id,nombre', 'sectores:id,nombre',
                'seguimientos.dependencia:id,nombre', 'seguimientos.area:id,nombre',
                'verificaciones',
            ])
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
            // El histórico son las cerradas que no se volvieron obra; las
            // atendidas son las cerradas, sin importar la obra.
            ->when($f['vista'] === 'historico', fn ($q) => $q
                ->whereIn('status', [2, 5, 6])
                ->where('convertida_obra', Solicitud::OBRA_NO))
            ->when($f['vista'] === 'atendidas', fn ($q) => $q
                ->whereIn('status', [2, 5, 6]))
            ->when(
                $f['vista'] === 'atendidas',
                fn ($q) => $q->orderByDesc('fecha_resp'),
                fn ($q) => $q->orderByDesc('fecha_recepcion'),
            );
    }

    /**
     * @param  list<string>  $columnas
     * @return array<string,mixed>
     */
    private function filaGestion(Solicitud $s, array $columnas): array
    {
        $solicitante = $s->solicitante instanceof Beneficiario
            ? $s->solicitante->nombreCompleto()
            : $s->solicitante?->nombre;

        // El legacy toma el último seguimiento para dependencia, área y respuesta.
        $ultimo = $s->seguimientos->sortByDesc('id')->first();

        $todas = [
            'solicitante' => $solicitante,
            'direccion' => $this->direccion($s->solicitante),
            'foliosolicitud' => $s->folio ?: $s->folio_sistema,
            'solicitud' => $s->solicitud,
            'estatus' => $s->estatusLabel(),
            'localidad' => $s->localidadResp?->nombre,
            'recepcionsol' => $s->fecha_recepcion?->toDateString(),
            'origen' => $s->procedencia?->nombre,
            'apoyo' => $s->apoyo,
            'cantidad' => $s->cantidad,
            'monto' => $s->monto,
            'beneficiarios' => $s->num_bene,
            'rubros' => $s->rubros->pluck('nombre')->implode(', ') ?: null,
            'sectores' => $s->sectores->pluck('nombre')->implode(', ') ?: null,
            'concepto' => $s->concepto?->nombre,
            // 1 y 2 son individual en el legacy; 3 es comunitario.
            'tipoben' => match ($s->bene_final) {
                1, 2 => 'INDIVIDUAL',
                3 => 'COMUNITARIO',
                default => null,
            },
            'entrega' => $s->fecha_resp,
            'respuesta' => $this->unir(' — ', [$ultimo?->instruccion, $ultimo?->respuesta]),
            'dpndnc' => $ultimo?->dependencia?->nombre,
            'area' => $ultimo?->area?->nombre,
            'vrfcr' => $s->verificaciones
                ->sortByDesc('fecha')
                ->map(fn (Verificacion $v): string => $this->resumenVerificacion($v))
                ->implode(' | ') ?: null,
            'tp_sol' => $s->control_administrativo ? 'DE CONTROL ADMINISTRATIVO' : 'CIUDADANA',
        ];

        return array_intersect_key($todas, array_flip($columnas));
    }

    private function resumenVerificacion(Verificacion $v): string
    {
        return $this->unir(': ', [
            $v->fecha,
            $this->unir(', ', [
                $v->atendido ? 'atendido' : 'no atendido',
                $v->satisfecho ? 'satisfecho' : 'no satisfecho',
                $v->comentario,
            ]),
        ]) ?? '';
    }

    /** @return array<string,mixed> */
    private function filtrosGestion(Request $request): array
    {
        $estatusRaw = $request->input('estatus', []);
        $estatusArr = is_array($estatusRaw) ? $estatusRaw : explode(',', (string) $estatusRaw);

        $vista = $request->string('vista')->toString();
        $vista = isset(self::VISTAS_GESTION[$vista]) ? $vista : 'detalle';

        return [
            ...$this->rangoFechas($request),
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
            'vista' => $vista,
            // Histórico y Atendidas no eligen columnas en el legacy: van
            // completas, aunque la URL pida otra cosa.
            'columnas' => in_array($vista, ['historico', 'atendidas'], true)
                ? array_keys($this->catalogoGestion($vista))
                : $this->columnasPedidas(
                    $request,
                    $this->catalogoGestion($vista),
                    $vista === 'general'
                        ? self::COLUMNAS_GESTION_GENERAL_POR_OMISION
                        : self::COLUMNAS_GESTION_POR_OMISION,
                ),
        ];
    }

    // ============================================================== OBRA
    /**
     * Las 42 casillas de `reportes/obra.php`. Varias no son campos sino
     * acuses del expediente (`tbl_obra_documentos`), y el avance sale de la
     * última estimación.
     *
     * @var array<string, string>
     */
    public const COLUMNAS_OBRA = [
        'nomobra' => 'Nombre de la obra',
        'numobra' => 'Número de obra',
        'localidad' => 'Localidad',
        'invfed' => 'Inversión Federal',
        'invest' => 'Inversión Estatal',
        'invmun' => 'Inversión Municipal',
        'invben' => 'Inversión de beneficiarios',
        'invotros' => 'Inversión de otros',
        'invtot' => 'Inversión total',
        'modalidad' => 'Modalidad de ejecución',
        'ejercicio' => 'Ejercicio fiscal',
        'ramo' => 'Ramo',
        'fondo' => 'Fondo',
        'programa' => 'Programa',
        'subprograma' => 'Subprograma',
        'numben' => 'Número de beneficiarios',
        'fecha_aut' => 'Fecha de autorización',
        'numaut' => 'Núm. oficio de autorización',
        'meta' => 'Meta (unidad / cantidad)',
        'numcont' => 'Número de contrato / convenio',
        'contratista' => 'Contratista',
        'montocont' => 'Monto contratado',
        'replegal' => 'Representante legal',
        'numlicit' => 'Número de licitación',
        'numconv' => 'Número de convocatoria',
        'dictamen' => 'Dictamen',
        'fallo' => 'Fallo',
        'actafallo' => 'Acta de fallo',
        'periodo' => 'Periodo del contrato',
        'dictamencom' => 'Dictamen de aprobación de comité',
        'avfis' => 'Avance físico',
        'avfin' => 'Avance financiero',
        'perinicio' => 'Periodo de la orden de inicio',
        'avconc' => 'Aviso de conclusión',
        'fechavconc' => 'Fecha del aviso de conclusión',
        'actafis' => 'Acta de entrega de trabajos físicos',
        'fechactafis' => 'Fecha del acta de trabajos físicos',
        'expcom' => 'Expediente unitario completo',
        'actaer' => 'Acta de entrega-recepción',
        'fechaer' => 'Fecha del acta de entrega-recepción',
        'pao' => 'Programa anual de obra',
        'anio' => 'Año del programa anual',
    ];

    private const COLUMNAS_OBRA_POR_OMISION = [
        'nomobra', 'numobra', 'localidad', 'invtot', 'modalidad',
        'numcont', 'contratista', 'montocont', 'avfis',
    ];

    public function obra(Request $request): Response
    {
        $f = $this->filtrosObra($request);
        $obras = $this->queryObra($f)->paginate(20)->withQueryString();

        return Inertia::render('reportes/obra', [
            'filtros' => $f,
            'opciones' => [
                'modalidades' => $this->comoOpciones(CamposObra::CATALOGOS['mod_ejecucion']),
                'contrataciones' => $this->comoOpciones(CamposObra::CATALOGOS['tipo_contratacion']),
                'columnas' => $this->catalogo(self::COLUMNAS_OBRA),
            ],
            'filas' => $obras->getCollection()
                ->map(fn (Obra $o): array => $this->filaObra($o, $f['columnas']))
                ->values(),
            'paginacion' => $this->paginacion($obras),
        ]);
    }

    public function obraExcel(Request $request): BinaryFileResponse
    {
        $f = $this->filtrosObra($request);

        return Excel::download(new QueryExport(
            fn (): Builder => $this->queryObra($f),
            array_map(fn (string $c): string => self::COLUMNAS_OBRA[$c], $f['columnas']),
            fn (Obra $o): array => array_values($this->filaObra($o, $f['columnas'])),
        ), 'reporte_obra.xlsx');
    }

    /** @param array<string,mixed> $f */
    private function queryObra(array $f): Builder
    {
        return Obra::query()
            ->with([
                'localidad:id,nombre', 'solicitud:id,solicitud',
                'autorizacion', 'adjudicacion', 'ejecucion', 'cierre',
                'documentos:id,obra_id,clave,presente',
                'estimaciones:id,obra_id,num_estimacion,avance_fisico,avance_financiero',
            ])
            ->when($f['ejercicio'] !== '', fn ($q) => $q->whereHas(
                'autorizacion',
                fn ($a) => $a->where('ejercicio_fiscal', $f['ejercicio']),
            ))
            ->when($f['modalidad'], fn ($q) => $q->whereHas(
                'autorizacion',
                fn ($a) => $a->where('mod_ejecucion', (string) $f['modalidad']),
            ))
            ->when($f['tipo_contratacion'], fn ($q) => $q->whereHas(
                'autorizacion',
                fn ($a) => $a->where('tipo_contratacion', (string) $f['tipo_contratacion']),
            ))
            ->orderBy('id');
    }

    /**
     * @param  list<string>  $columnas
     * @return array<string,mixed>
     */
    private function filaObra(Obra $o, array $columnas): array
    {
        $aut = $o->autorizacion;
        $adj = $o->adjudicacion;
        $eje = $o->ejecucion;
        $cie = $o->cierre;

        // El acuse de un papel se lee como en la pantalla: Sí / No / vacío.
        $acuse = function (string $clave) use ($o): ?string {
            $doc = $o->documentos->firstWhere('clave', $clave);

            return $doc?->presente === null ? null : ($doc->presente ? 'Sí' : 'No');
        };

        // El avance lo manda la estimación más reciente que lo traiga.
        $ultima = $o->estimaciones
            ->filter(fn ($e) => filled($e->avance_fisico) || filled($e->avance_financiero))
            ->last();

        $rango = fn (?string $de, ?string $a) => $this->unir(' – ', [$de, $a]);

        $todas = [
            'nomobra' => $aut?->nombre_obra_aut ?: ($o->nombre ?: $o->solicitud?->solicitud),
            'numobra' => $o->num_obra,
            'localidad' => $o->localidad?->nombre,
            'invfed' => $aut?->aporte_fed,
            'invest' => $aut?->aporte_est,
            'invmun' => $aut?->aporte_mun,
            'invben' => $aut?->aporte_bene,
            'invotros' => $aut?->aporte_otr,
            'invtot' => $o->monto_inv,
            'modalidad' => CamposObra::CATALOGOS['mod_ejecucion'][(int) $aut?->mod_ejecucion] ?? null,
            'ejercicio' => $aut?->ejercicio_fiscal,
            'ramo' => $aut?->ramo,
            'fondo' => $aut?->fondo,
            'programa' => $aut?->finan_prog,
            'subprograma' => $aut?->finan_subprog,
            'numben' => $o->num_benef,
            'fecha_aut' => $aut?->fecha_oficio_aut?->toDateString(),
            'numaut' => $aut?->num_oficio_aut,
            'meta' => $this->unir(' / ', [$aut?->metas_unidad, $aut?->metas_cantidad]),
            'numcont' => $adj?->num_contrato ?: $adj?->num_convenio,
            'contratista' => $adj?->nom_empresa,
            'montocont' => $adj?->monto_contrato,
            'replegal' => $adj?->rep_empresa,
            'numlicit' => $adj?->num_licitacion,
            'numconv' => $adj?->num_convocatoria,
            'dictamen' => $acuse('dictamen_contrato'),
            'fallo' => $acuse('fallo_contrato'),
            'actafallo' => $acuse('acta_adj_contrato'),
            'periodo' => $rango(
                $adj?->fecha_inicio_obra?->toDateString(),
                $adj?->fecha_termino_obra?->toDateString(),
            ),
            'dictamencom' => $acuse('dic_aprov_comite'),
            'avfis' => $ultima?->avance_fisico,
            'avfin' => $ultima?->avance_financiero,
            'perinicio' => $rango(
                $eje?->fecha_orden_inicio?->toDateString(),
                $eje?->fecha_termino_orden?->toDateString(),
            ),
            'avconc' => $acuse('aviso_conclusion'),
            'fechavconc' => $cie?->fecha_aviso_conclusion?->toDateString(),
            'actafis' => $cie?->acta_entrega_fisica,
            'fechactafis' => $cie?->fecha_acta_entrega_fisica?->toDateString(),
            'expcom' => $acuse('unitario_completo'),
            'actaer' => $cie?->acta_entrega_recepcion,
            'fechaer' => $cie?->fecha_acta_entrega_recepcion?->toDateString(),
            'pao' => $acuse('prog_anual'),
            'anio' => $aut?->anio_prog_anual,
        ];

        return array_intersect_key($todas, array_flip($columnas));
    }

    /** @return array<string,mixed> */
    private function filtrosObra(Request $request): array
    {
        return [
            'ejercicio' => trim($request->string('ejercicio')->toString()),
            'modalidad' => $request->integer('modalidad') ?: null,
            'tipo_contratacion' => $request->integer('tipo_contratacion') ?: null,
            'columnas' => $this->columnasPedidas(
                $request,
                self::COLUMNAS_OBRA,
                self::COLUMNAS_OBRA_POR_OMISION,
            ),
        ];
    }

    /**
     * @param  array<int,string>  $catalogo
     * @return list<array{id: int, nombre: string}>
     */
    private function comoOpciones(array $catalogo): array
    {
        return collect($catalogo)
            ->map(fn (string $nombre, int $id): array => ['id' => $id, 'nombre' => $nombre])
            ->values()
            ->all();
    }

    // ============================================================== Seguridad
    /**
     * Columnas que el usuario puede pedir, como las casillas del legacy
     * `reportes/seguridad.php`. La clave es lo que viaja en la URL.
     *
     * @var array<string, string>
     */
    public const COLUMNAS_SEGURIDAD = [
        'denunciante' => 'Denunciante',
        'fecha' => 'Fecha de reporte',
        'origen' => 'Origen de denuncia',
        'denuncia' => 'Denuncia',
        'quien' => 'Quién atiende',
        'fecha_atencion' => 'Fecha de atención',
        'situacion' => 'Descripción de la situación',
        'delito' => 'Presunto delito',
        'violencia' => 'Nivel de violencia',
        'localidad' => 'Localidad',
        'sector' => 'Sector',
        'acciones' => 'Acciones',
        'acuerdos' => 'Acuerdos y convenios',
        'conclusion' => 'Conclusión',
        'estatus' => 'Estatus',
        'detenidos' => 'Detenidos',
    ];

    /** Las que vienen marcadas si el usuario no elige. */
    private const COLUMNAS_SEGURIDAD_POR_OMISION = [
        'denunciante', 'fecha', 'denuncia', 'delito', 'localidad', 'estatus',
    ];

    public function seguridad(Request $request): Response
    {
        $f = $this->filtrosSeguridad($request);
        $denuncias = $this->querySeguridad($f)->paginate(20)->withQueryString();

        return Inertia::render('reportes/seguridad', [
            'filtros' => $f,
            'opciones' => [
                'origenes' => CatOrigenDenuncia::query()->orderBy('nombre')->get(['id', 'nombre']),
                'delitos' => CatTipoIncidencia::query()->orderBy('nombre')->get(['id', 'nombre']),
                'niveles' => CatNivelViolencia::query()->get(['id', 'nombre']),
                'sectores' => CatSegSector::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
                'etapas' => [
                    ['id' => 1, 'nombre' => 'Recepción'],
                    ['id' => 2, 'nombre' => 'Atención'],
                    ['id' => 3, 'nombre' => 'Término'],
                ],
                'columnas' => $this->catalogo(self::COLUMNAS_SEGURIDAD),
            ],
            'filas' => $denuncias->getCollection()
                ->map(fn (Denuncia $d): array => $this->filaSeguridad($d, $f['columnas']))
                ->values(),
            'paginacion' => $this->paginacion($denuncias),
        ]);
    }

    public function seguridadExcel(Request $request): BinaryFileResponse
    {
        $f = $this->filtrosSeguridad($request);

        return Excel::download(new QueryExport(
            fn (): Builder => $this->querySeguridad($f),
            array_map(fn (string $c): string => self::COLUMNAS_SEGURIDAD[$c], $f['columnas']),
            fn (Denuncia $d): array => array_values($this->filaSeguridad($d, $f['columnas'])),
        ), 'reporte_seguridad.xlsx');
    }

    /** @param array<string,mixed> $f */
    private function querySeguridad(array $f): Builder
    {
        return Denuncia::query()
            ->with([
                'origenDenuncia:id,nombre', 'tipoIncidencia:id,nombre', 'nivelViolencia:id,nombre',
                'segSector:id,nombre', 'localidad:id,nombre', 'detenidos:id,denuncia_id,nombre,paterno,materno',
            ])
            ->when($f['desde'], fn ($q) => $q->whereDate('fecha_denuncia', '>=', $f['desde']))
            ->when($f['hasta'], fn ($q) => $q->whereDate('fecha_denuncia', '<=', $f['hasta']))
            ->when($f['origenes'] !== [], fn ($q) => $q->whereIn('origen_denuncia_id', $f['origenes']))
            ->when($f['delitos'] !== [], fn ($q) => $q->whereIn('tipo_incidencia_id', $f['delitos']))
            ->when($f['niveles'] !== [], fn ($q) => $q->whereIn('nivel_violencia_id', $f['niveles']))
            ->when($f['sectores'] !== [], fn ($q) => $q->whereIn('seg_sector_id', $f['sectores']))
            ->when($f['localidad_id'], fn ($q) => $q->where('localidad_id', $f['localidad_id']))
            ->when($f['etapas'] !== [], fn ($q) => $q->where(function ($sub) use ($f): void {
                // 1 recepción · 2 atención · 3 término, como el legacy.
                if (in_array(1, $f['etapas'], true)) {
                    $sub->orWhere(fn ($e) => $e->where('con_atencion', false)->where('con_termino', false));
                }
                if (in_array(2, $f['etapas'], true)) {
                    $sub->orWhere(fn ($e) => $e->where('con_atencion', true)->where('con_termino', false));
                }
                if (in_array(3, $f['etapas'], true)) {
                    $sub->orWhere('con_termino', true);
                }
            }))
            ->orderByDesc('fecha_denuncia')
            ->orderByDesc('id');
    }

    /**
     * Sólo las columnas pedidas, en el orden del catálogo.
     *
     * @param  list<string>  $columnas
     * @return array<string,mixed>
     */
    private function filaSeguridad(Denuncia $d, array $columnas): array
    {
        $todas = [
            'denunciante' => $d->anonimo ? 'Anónimo' : $d->denunciante(),
            'fecha' => $d->fecha_denuncia,
            'origen' => $d->origenDenuncia?->nombre,
            'denuncia' => $d->denuncia,
            'quien' => $d->atendido_por,
            'fecha_atencion' => $d->fecha_atencion,
            'situacion' => $d->descripcion_situacion,
            'delito' => $d->tipoIncidencia?->nombre,
            'violencia' => $d->nivelViolencia?->nombre,
            'localidad' => $d->localidad?->nombre,
            'sector' => $d->segSector?->nombre,
            'acciones' => $d->acciones,
            'acuerdos' => $d->acuerdos_convenios,
            'conclusion' => $d->conclusion,
            'estatus' => $d->estatusLabel(),
            'detenidos' => $d->detenidos->map(fn (Detenido $x): string => $x->nombreCompleto())->implode(', '),
        ];

        return array_intersect_key($todas, array_flip($columnas));
    }

    /** @return array<string,mixed> */
    private function filtrosSeguridad(Request $request): array
    {
        $lista = function (string $campo) use ($request): array {
            $raw = $request->input($campo, []);
            $arr = is_array($raw) ? $raw : explode(',', (string) $raw);

            return array_values(array_filter(array_map('intval', $arr)));
        };

        return [
            ...$this->rangoFechas($request),
            'origenes' => $lista('origenes'),
            'delitos' => $lista('delitos'),
            'niveles' => $lista('niveles'),
            'sectores' => $lista('sectores'),
            'etapas' => array_values(array_filter($lista('etapas'), fn ($v) => $v >= 1 && $v <= 3)),
            'localidad_id' => $request->integer('localidad_id') ?: null,
            'columnas' => $this->columnasPedidas(
                $request,
                self::COLUMNAS_SEGURIDAD,
                self::COLUMNAS_SEGURIDAD_POR_OMISION,
            ),
        ];
    }

    // ============================================================= Utilidades
    /**
     * Columnas pedidas, filtradas contra el catálogo y devueltas **en el orden
     * del catálogo**, no en el que se marcaron: así la tabla y el Excel salen
     * siempre iguales. Si no se pide ninguna, van las de por omisión.
     *
     * @param  array<string,string>  $catalogo
     * @param  list<string>  $porOmision
     * @return list<string>
     */
    /**
     * Rango de fechas del filtro, ya validado.
     *
     * `Request::date()` revienta con una excepción si el valor no se puede
     * parsear, así que primero se valida: una fecha mal escrita en la URL
     * tiene que devolver un error de campo, no un 500.
     *
     * @return array{desde: ?string, hasta: ?string}
     */
    private function rangoFechas(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        return [
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
        ];
    }

    private function columnasPedidas(Request $request, array $catalogo, array $porOmision): array
    {
        $raw = $request->input('columnas');
        $pedidas = is_array($raw) ? $raw : ($raw ? explode(',', (string) $raw) : []);
        $pedidas = array_filter($pedidas, fn ($c) => isset($catalogo[$c]));

        if ($pedidas === []) {
            return $porOmision;
        }

        return array_values(array_filter(
            array_keys($catalogo),
            fn (string $c): bool => in_array($c, $pedidas, true),
        ));
    }

    /**
     * El catálogo tal como lo consume el front (lista ordenada).
     *
     * @param  array<string,string>  $catalogo
     * @return list<array{clave: string, label: string}>
     */
    private function catalogo(array $catalogo): array
    {
        return collect($catalogo)
            ->map(fn (string $label, string $clave): array => ['clave' => $clave, 'label' => $label])
            ->values()
            ->all();
    }

    /** Une las partes no vacías; null si no queda nada. */
    private function unir(string $pegamento, array $partes): ?string
    {
        $limpias = array_filter(
            array_map(fn ($p): string => trim((string) $p), $partes),
            fn (string $p): bool => $p !== '',
        );

        return $limpias === [] ? null : implode($pegamento, $limpias);
    }

    /** Calle, número, colonia y C.P. en un solo renglón. */
    private function direccion(?object $m): ?string
    {
        if ($m === null || ! isset($m->calle)) {
            return null;
        }

        $calle = $this->unir(' ', [
            $m->calle,
            $m->num_ext ? "#{$m->num_ext}" : null,
            $m->num_int ? "int. {$m->num_int}" : null,
        ]);

        return $this->unir(', ', [$calle, $m->colonia, $m->cp ? "C.P. {$m->cp}" : null]);
    }

    /** "38 años (1987-03-04)", como la columna "Edad / Nacimiento" del legacy. */
    private function edadYNacimiento(mixed $nacimiento): ?string
    {
        if (! $nacimiento) {
            return null;
        }

        $fecha = $nacimiento instanceof \DateTimeInterface
            ? CarbonImmutable::instance($nacimiento)
            : CarbonImmutable::parse((string) $nacimiento);

        return $fecha->age.' años ('.$fecha->toDateString().')';
    }

    /** Lee un filtro booleano de 3 estados: null (todos) / true / false. */
    private function tri(Request $request, string $campo): ?bool
    {
        $v = $request->input($campo);

        return ($v === null || $v === '') ? null : (bool) (int) $v;
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
