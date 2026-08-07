<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\CatAntagonico;
use App\Models\CatEstado;
use App\Models\CatEstadoCivil;
use App\Models\CatFiliacion;
use App\Models\CatInfluencia;
use App\Models\CatLider;
use App\Models\CatOcupacion;
use App\Models\CatProfesion;
use App\Models\CatSector;
use App\Models\CatSectorOrganizacion;
use App\Models\Organizacion;
use App\Models\Proveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Directorio en una sola pantalla con tres pestañas, como el legacy
 * `directorio/index.php`: Individuos, Asociaciones / Grupos organizados y
 * Proveedores / Contratistas. No lista nada hasta que se lanza una búsqueda.
 *
 * Los filtros "Sección" y "Militancia" del legacy no existen aquí: son campos
 * electorales que quedaron fuera del alcance de la migración.
 */
class DirectorioController extends Controller
{
    /** Tarjetas por pestaña (la rejilla del legacy es de 3 por fila). */
    private const POR_PAGINA = 24;

    /**
     * Paginadores de cada pestaña, guardados al construir cada listado para
     * poder devolver sus metadatos sin repetir la consulta.
     *
     * @var array<string, LengthAwarePaginator<int, mixed>>
     */
    private array $paginadores = [];

    /**
     * Casillas "Buscar en" del legacy, con su valor por omisión.
     *
     * @var array<string, bool>
     */
    private const CAMPOS_BUSQUEDA = [
        'nombres' => true,
        'comentarios' => false,
        'trabajo' => false,
    ];

    public function index(Request $request): Response
    {
        $q = $request->string('q')->trim()->toString();
        $campos = $this->camposBusqueda($request);

        $filtros = [
            'estado_id' => $request->integer('estado_id') ?: null,
            'municipio_id' => $request->integer('municipio_id') ?: null,
            'localidad_id' => $request->integer('localidad_id') ?: null,
            'profesion_id' => $request->integer('profesion_id') ?: null,
            'sector_id' => $request->integer('sector_id') ?: null,
            'genero' => $request->integer('genero') ?: null,
        ];

        $buscado = $q !== '' || array_filter($filtros) !== [];

        return Inertia::render('directorio/index', [
            'buscado' => $buscado,
            'tab' => $request->string('tab')->toString() ?: 'individuos',
            // Llega desde "Editar solicitante" en Solicitudes: abre el diálogo.
            'editarId' => $request->integer('editar') ?: null,
            'filtros' => [...$filtros, 'q' => $q, 'campos' => $campos],
            'individuos' => $buscado ? $this->individuos($q, $campos, $filtros) : [],
            'organizaciones' => $buscado ? $this->organizaciones($q, $campos, $filtros) : [],
            'proveedores' => $buscado ? $this->proveedores($q, $campos, $filtros) : [],
            'paginacion' => $buscado ? [
                'individuos' => $this->paginacion($this->paginadores['individuos'] ?? null),
                'organizaciones' => $this->paginacion($this->paginadores['organizaciones'] ?? null),
                'proveedores' => $this->paginacion($this->paginadores['proveedores'] ?? null),
            ] : ['individuos' => null, 'organizaciones' => null, 'proveedores' => null],
            'opciones' => [
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
                'sectores' => CatSector::query()->orderBy('nombre')->get(['id', 'nombre']),
                'sectoresOrganizacion' => CatSectorOrganizacion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'ocupaciones' => CatOcupacion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'profesiones' => CatProfesion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estadosCiviles' => CatEstadoCivil::query()->orderBy('nombre')->get(['id', 'nombre']),
                'filiaciones' => CatFiliacion::query()->orderBy('id')->get(['id', 'nombre']),
                'antagonicos' => CatAntagonico::query()->orderBy('id')->get(['id', 'nombre']),
                'lideres' => CatLider::query()->orderBy('id')->get(['id', 'nombre']),
                'influencias' => CatInfluencia::query()->orderBy('id')->get(['id', 'nombre']),
            ],
        ]);
    }

    /**
     * Registro completo para precargar el formulario de edición. Va aparte del
     * listado porque incluye la foto (data URI) y todos los campos del alta.
     */
    public function registro(string $tipo, int $id): JsonResponse
    {
        $datos = match ($tipo) {
            'beneficiarios' => $this->registroBeneficiario($id),
            'organizaciones' => $this->registroOrganizacion($id),
            'proveedores' => $this->registroProveedor($id),
            default => abort(404),
        };

        return response()->json($datos);
    }

    /**
     * @return array<string, mixed>
     */
    private function registroBeneficiario(int $id): array
    {
        $b = Beneficiario::query()->with(['municipio:id,nombre', 'localidad:id,nombre'])->findOrFail($id);

        return [
            ...$b->only([
                'id', 'nombre', 'paterno', 'materno', 'alias', 'curp', 'genero', 'tipo',
                'estado_civil_id', 'calle', 'num_ext', 'num_int', 'colonia', 'cp',
                'estado_id', 'municipio_id', 'localidad_id', 'telefono', 'celular', 'celular2',
                'correo', 'correo2', 'facebook', 'twitter', 'empresa', 'puesto', 'tel_empresa',
                'ocupacion_id', 'profesion_id', 'sector_id', 'grupo',
                'vinculo_municipal', 'vinculo_estatal', 'vinculo_federal',
                'asist_nombre', 'asist_movil', 'asist_correo',
                'conyuge_nombre', 'conyuge_movil', 'estatus', 'foto',
            ]),
            'nacimiento' => $b->nacimiento?->toDateString(),
            'conyuge_nacimiento' => $b->conyuge_nacimiento?->toDateString(),
            'municipio_nombre' => $b->municipio?->nombre,
            'localidad_nombre' => $b->localidad?->nombre,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registroOrganizacion(int $id): array
    {
        $o = Organizacion::query()
            ->with(['municipio:id,nombre', 'localidad:id,nombre', 'representante:id,nombre,paterno,materno'])
            ->findOrFail($id);

        return [
            ...$o->only([
                'id', 'nombre', 'tipo', 'sector_organizacion_id', 'representante_id',
                'calle', 'num_ext', 'num_int', 'colonia', 'cp',
                'estado_id', 'municipio_id', 'localidad_id',
                'telefono', 'celular', 'correo', 'foto',
            ]),
            'representante_nombre' => $o->representante?->nombreCompleto(),
            'municipio_nombre' => $o->municipio?->nombre,
            'localidad_nombre' => $o->localidad?->nombre,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registroProveedor(int $id): array
    {
        $p = Proveedor::query()
            ->with(['municipio:id,nombre', 'localidad:id,nombre', 'representante:id,nombre,paterno,materno'])
            ->findOrFail($id);

        return [
            ...$p->only([
                'id', 'nombre', 'rfc', 'representante_id', 'especialidad', 'tipo',
                'calificacion', 'num_prov_gob', 'calle', 'num_ext', 'num_int', 'colonia', 'cp',
                'estado_id', 'municipio_id', 'localidad_id',
                'telefono', 'celular', 'correo', 'foto',
            ]),
            'representante_nombre' => $p->representante?->nombreCompleto(),
            'municipio_nombre' => $p->municipio?->nombre,
            'localidad_nombre' => $p->localidad?->nombre,
        ];
    }

    /**
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
     * Filtros de ubicación, comunes a las tres pestañas.
     *
     * @param  array<string, int|null>  $filtros
     */
    private function filtrarUbicacion(Builder $q, array $filtros): void
    {
        foreach (['estado_id', 'municipio_id', 'localidad_id'] as $columna) {
            if ($filtros[$columna] !== null) {
                $q->where($columna, $filtros[$columna]);
            }
        }
    }

    /**
     * Busca en los comentarios (tabla polimórfica) de la entidad.
     */
    private function enComentarios(Builder $q, string $like): void
    {
        $q->orWhereHas('comentarios', fn (Builder $c) => $c->whereLike('comentario', $like));
    }

    /**
     * @param  array<string, bool>  $campos
     * @param  array<string, int|null>  $filtros
     * @return list<array<string, mixed>>
     */
    private function individuos(string $q, array $campos, array $filtros): array
    {
        $like = '%'.$q.'%';
        $palabras = $q === '' ? [] : (preg_split('/\s+/', $q) ?: []);

        $consulta = Beneficiario::query()
            ->with([
                'municipio:id,nombre', 'localidad:id,nombre', 'ocupacion:id,nombre',
                'filiacion:id,nombre', 'antagonico:id,nombre', 'lider:id,nombre', 'influencia:id,nombre',
            ])
            ->tap(fn (Builder $b) => $this->filtrarUbicacion($b, $filtros));

        if ($filtros['profesion_id'] !== null) {
            $consulta->where('profesion_id', $filtros['profesion_id']);
        }
        if ($filtros['sector_id'] !== null) {
            $consulta->where('sector_id', $filtros['sector_id']);
        }
        if ($filtros['genero'] !== null) {
            $consulta->where('genero', $filtros['genero']);
        }

        if ($q !== '') {
            $consulta->where(function (Builder $w) use ($campos, $like, $palabras): void {
                if ($campos['nombres']) {
                    $w->orWhere(function (Builder $n) use ($palabras): void {
                        foreach ($palabras as $palabra) {
                            $n->where(fn (Builder $p) => $p
                                ->whereLike('nombre', '%'.$palabra.'%')
                                ->orWhereLike('paterno', '%'.$palabra.'%')
                                ->orWhereLike('materno', '%'.$palabra.'%')
                                ->orWhereLike('alias', '%'.$palabra.'%'));
                        }
                    });
                }
                if ($campos['trabajo']) {
                    $w->orWhereLike('empresa', $like)->orWhereLike('puesto', $like);
                }
                if ($campos['comentarios']) {
                    $this->enComentarios($w, $like);
                }
            });
        }

        $pagina = $consulta
            ->orderBy('paterno')->orderBy('materno')->orderBy('nombre')
            ->paginate(self::POR_PAGINA, ['*'], 'pageInd')
            ->withQueryString();

        $this->paginadores['individuos'] = $pagina;

        return collect($pagina->items())
            ->map(fn (Beneficiario $b): array => [
                'id' => $b->id,
                'tipo' => 'beneficiarios',
                'titulo' => $b->nombreCompleto(),
                'subtitulo' => $b->alias,
                'etiqueta' => $b->ocupacion?->nombre,
                'ubicacion' => $this->ubicacion($b->localidad?->nombre, $b->municipio?->nombre),
                // Casi ningún registro tiene foto; avisarlo evita que cada
                // tarjeta pida una imagen que va a responder 404.
                'tiene_foto' => filled($b->foto),
                'celular' => $b->celular,
                'telefono' => $b->telefono,
                'correo' => $b->correo,
                'datos' => $this->datosBeneficiario($b),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $campos
     * @param  array<string, int|null>  $filtros
     * @return list<array<string, mixed>>
     */
    private function organizaciones(string $q, array $campos, array $filtros): array
    {
        $like = '%'.$q.'%';

        $consulta = Organizacion::query()
            ->with(['municipio:id,nombre', 'localidad:id,nombre', 'sectorOrganizacion:id,nombre', 'representante:id,nombre,paterno,materno'])
            ->tap(fn (Builder $b) => $this->filtrarUbicacion($b, $filtros));

        if ($q !== '') {
            $consulta->where(function (Builder $w) use ($campos, $like): void {
                if ($campos['nombres']) {
                    $w->orWhereLike('nombre', $like);
                }
                if ($campos['comentarios']) {
                    $this->enComentarios($w, $like);
                }
            });
        }

        $pagina = $consulta->orderBy('nombre')
            ->paginate(self::POR_PAGINA, ['*'], 'pageOrg')
            ->withQueryString();

        $this->paginadores['organizaciones'] = $pagina;

        return collect($pagina->items())
            ->map(fn (Organizacion $o): array => [
                'id' => $o->id,
                'tipo' => 'organizaciones',
                'titulo' => $o->nombre,
                'subtitulo' => $o->sectorOrganizacion?->nombre,
                'etiqueta' => $o->representante?->nombreCompleto(),
                'ubicacion' => $this->ubicacion($o->localidad?->nombre, $o->municipio?->nombre),
                'tiene_foto' => filled($o->foto),
                'celular' => $o->celular,
                'telefono' => $o->telefono,
                'correo' => $o->correo,
                'datos' => [
                    'Tipo de organización' => $o->sectorOrganizacion?->nombre,
                    'Representante' => $o->representante?->nombreCompleto(),
                    'Domicilio' => $this->domicilio($o),
                    'Teléfono' => $o->telefono,
                    'Celular' => $o->celular,
                    'Correo' => $o->correo,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $campos
     * @param  array<string, int|null>  $filtros
     * @return list<array<string, mixed>>
     */
    private function proveedores(string $q, array $campos, array $filtros): array
    {
        $like = '%'.$q.'%';

        $consulta = Proveedor::query()
            ->with(['municipio:id,nombre', 'localidad:id,nombre', 'representante:id,nombre,paterno,materno'])
            ->tap(fn (Builder $b) => $this->filtrarUbicacion($b, $filtros));

        if ($q !== '') {
            $consulta->where(function (Builder $w) use ($campos, $like): void {
                if ($campos['nombres']) {
                    $w->orWhereLike('nombre', $like)->orWhereLike('rfc', $like);
                }
                if ($campos['trabajo']) {
                    $w->orWhereLike('especialidad', $like);
                }
                if ($campos['comentarios']) {
                    $this->enComentarios($w, $like);
                }
            });
        }

        $pagina = $consulta->orderBy('nombre')
            ->paginate(self::POR_PAGINA, ['*'], 'pageProv')
            ->withQueryString();

        $this->paginadores['proveedores'] = $pagina;

        return collect($pagina->items())
            ->map(fn (Proveedor $p): array => [
                'id' => $p->id,
                'tipo' => 'proveedores',
                'titulo' => $p->nombre,
                'subtitulo' => $p->especialidad,
                'etiqueta' => $p->rfc,
                'ubicacion' => $this->ubicacion($p->localidad?->nombre, $p->municipio?->nombre),
                'tiene_foto' => filled($p->foto),
                'celular' => $p->celular,
                'telefono' => $p->telefono,
                'correo' => $p->correo,
                'datos' => [
                    'RFC' => $p->rfc,
                    'Especialidad' => $p->especialidad,
                    'Representante legal' => $p->representante?->nombreCompleto(),
                    'Calificación' => $p->calificacion,
                    'Núm. proveedor gobierno' => $p->num_prov_gob,
                    'Domicilio' => $this->domicilio($p),
                    'Teléfono' => $p->telefono,
                    'Celular' => $p->celular,
                    'Correo' => $p->correo,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Ficha del legacy (`info_contacto.php`): los datos que no caben en la
     * tarjeta pero se consultan al abrirla.
     *
     * @return array<string, string|null>
     */
    private function datosBeneficiario(Beneficiario $b): array
    {
        return [
            'Alias' => $b->alias,
            'CURP' => $b->curp,
            'Fecha de nacimiento' => $b->nacimiento?->toDateString(),
            'Estado civil' => $b->estadoCivil?->nombre,
            'Ocupación' => $b->ocupacion?->nombre,
            'Profesión' => $b->profesion?->nombre,
            'Sector' => $b->sector?->nombre,
            'Domicilio' => $this->domicilio($b),
            'Celular principal' => $b->celular,
            'Celular secundario' => $b->celular2,
            'Particular' => $b->telefono,
            'Trabajo' => $b->tel_empresa,
            'Correo principal' => $b->correo,
            'Correo secundario' => $b->correo2,
            'Empresa' => $b->empresa,
            'Puesto' => $b->puesto,
            'Grupo' => $b->grupo,
            'Vínculo municipal' => $b->vinculo_municipal,
            'Vínculo estatal' => $b->vinculo_estatal,
            'Vínculo federal' => $b->vinculo_federal,
            'Asistente' => $b->asist_nombre,
            'Móvil del asistente' => $b->asist_movil,
            'Correo del asistente' => $b->asist_correo,
            'Cónyuge' => $b->conyuge_nombre,
            'Móvil del cónyuge' => $b->conyuge_movil,
            'Facebook' => $b->facebook,
            'Twitter/X' => $b->twitter,
            // El perfil político va al final: es lo que menos registros traen.
            'Sección electoral' => $b->seccion,
            'Filiación' => $b->filiacion?->nombre,
            'Antagónico' => $b->antagonico?->nombre,
            'Líder' => $b->lider?->nombre,
            'Grado de influencia' => $b->influencia?->nombre,
        ];
    }

    /**
     * "Localidad, municipio" para la tarjeta. En muchos registros la localidad
     * es la cabecera municipal y ambos nombres coinciden; ahí se muestra uno.
     */
    private function ubicacion(?string $localidad, ?string $municipio): ?string
    {
        if ($localidad !== null && $municipio !== null && mb_strtolower($localidad) === mb_strtolower($municipio)) {
            return $municipio;
        }

        return trim(implode(', ', array_filter([$localidad, $municipio]))) ?: null;
    }

    private function domicilio(object $e): ?string
    {
        $calle = trim(implode(' ', array_filter([$e->calle, $e->num_ext, $e->num_int ? "int. {$e->num_int}" : null])));

        return trim(implode(', ', array_filter([$calle, $e->colonia, $e->cp ? "C.P. {$e->cp}" : null]))) ?: null;
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>|null  $p
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
}
