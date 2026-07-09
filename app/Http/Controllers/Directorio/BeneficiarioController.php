<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Directorio\UpsertBeneficiarioRequest;
use App\Models\Beneficiario;
use App\Models\CatEstado;
use App\Models\CatEstadoCivil;
use App\Models\CatMunicipio;
use App\Models\CatLocalidad;
use App\Models\CatOcupacion;
use App\Models\CatProfesion;
use App\Models\CatSector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BeneficiarioController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $beneficiarios = Beneficiario::query()
            ->with(['estado:id,nombre', 'municipio:id,nombre', 'localidad:id,nombre', 'sector:id,nombre'])
            ->when($busqueda !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('nombre', "%{$busqueda}%")
                ->orWhereLike('paterno', "%{$busqueda}%")
                ->orWhereLike('materno', "%{$busqueda}%")
                ->orWhereLike('curp', "%{$busqueda}%")
                ->orWhereLike('celular', "%{$busqueda}%")))
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('directorio/beneficiarios/index', [
            'beneficiarios' => $beneficiarios->getCollection()->map($this->serializar(...))->values(),
            'paginacion' => [
                'total' => $beneficiarios->total(),
                'currentPage' => $beneficiarios->currentPage(),
                'lastPage' => $beneficiarios->lastPage(),
                'prevUrl' => $beneficiarios->previousPageUrl(),
                'nextUrl' => $beneficiarios->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'opciones' => [
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
                'sectores' => CatSector::query()->orderBy('nombre')->get(['id', 'nombre']),
                'ocupaciones' => CatOcupacion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'profesiones' => CatProfesion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estadosCiviles' => CatEstadoCivil::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function store(UpsertBeneficiarioRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $beneficiario = isset($data['id'])
            ? Beneficiario::query()->findOrFail((int) $data['id'])
            : new Beneficiario;

        unset($data['id']);

        if (! $beneficiario->exists) {
            $beneficiario->created_by = $request->user()?->id;
        }

        $beneficiario->fill($data)->save();

        return back()->with('success', $beneficiario->wasRecentlyCreated
            ? 'Beneficiario creado correctamente.'
            : 'Beneficiario actualizado correctamente.');
    }

    public function destroy(Beneficiario $beneficiario): RedirectResponse
    {
        $beneficiario->delete();

        return back()->with('success', 'Beneficiario eliminado correctamente.');
    }

    /**
     * Búsqueda ligera para selectores (p. ej. representante de organización).
     */
    public function buscar(Request $request): \Illuminate\Http\JsonResponse
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $resultados = Beneficiario::query()
            ->when($busqueda !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('nombre', "%{$busqueda}%")
                ->orWhereLike('paterno', "%{$busqueda}%")
                ->orWhereLike('materno', "%{$busqueda}%")))
            ->orderBy('paterno')
            ->limit(20)
            ->get(['id', 'nombre', 'paterno', 'materno'])
            ->map(fn (Beneficiario $b): array => [
                'id' => $b->id,
                'nombre' => $b->nombreCompleto(),
            ]);

        return response()->json($resultados);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Beneficiario $b): array
    {
        return [
            'id' => $b->id,
            'nombre_completo' => $b->nombreCompleto(),
            'nombre' => $b->nombre,
            'paterno' => $b->paterno,
            'materno' => $b->materno,
            'alias' => $b->alias,
            'curp' => $b->curp,
            'genero' => $b->genero,
            'nacimiento' => $b->nacimiento?->toDateString(),
            'tipo' => $b->tipo,
            'estado_civil_id' => $b->estado_civil_id,
            'calle' => $b->calle,
            'num_ext' => $b->num_ext,
            'num_int' => $b->num_int,
            'colonia' => $b->colonia,
            'cp' => $b->cp,
            'estado_id' => $b->estado_id,
            'municipio_id' => $b->municipio_id,
            'localidad_id' => $b->localidad_id,
            'telefono' => $b->telefono,
            'celular' => $b->celular,
            'celular2' => $b->celular2,
            'correo' => $b->correo,
            'correo2' => $b->correo2,
            'facebook' => $b->facebook,
            'twitter' => $b->twitter,
            'empresa' => $b->empresa,
            'puesto' => $b->puesto,
            'tel_empresa' => $b->tel_empresa,
            'ocupacion_id' => $b->ocupacion_id,
            'profesion_id' => $b->profesion_id,
            'sector_id' => $b->sector_id,
            'grupo' => $b->grupo,
            'vinculo_municipal' => $b->vinculo_municipal,
            'vinculo_estatal' => $b->vinculo_estatal,
            'vinculo_federal' => $b->vinculo_federal,
            'asist_nombre' => $b->asist_nombre,
            'asist_movil' => $b->asist_movil,
            'asist_correo' => $b->asist_correo,
            'conyuge_nombre' => $b->conyuge_nombre,
            'conyuge_movil' => $b->conyuge_movil,
            'conyuge_nacimiento' => $b->conyuge_nacimiento?->toDateString(),
            'estatus' => $b->estatus,
            // Nombres para display / precargar selects dependientes.
            'estado_nombre' => $b->estado?->nombre,
            'municipio_nombre' => $b->municipio?->nombre,
            'localidad_nombre' => $b->localidad?->nombre,
            'sector_nombre' => $b->sector?->nombre,
        ];
    }
}
