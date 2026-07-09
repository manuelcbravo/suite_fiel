<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Directorio\UpsertOrganizacionRequest;
use App\Models\CatEstado;
use App\Models\CatSectorOrganizacion;
use App\Models\Organizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizacionController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $organizaciones = Organizacion::query()
            ->with([
                'sectorOrganizacion:id,nombre',
                'representante:id,nombre,paterno,materno',
                'estado:id,nombre',
                'municipio:id,nombre',
                'localidad:id,nombre',
            ])
            ->when($busqueda !== '', fn (Builder $q) => $q->whereLike('nombre', "%{$busqueda}%"))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('directorio/organizaciones/index', [
            'organizaciones' => $organizaciones->getCollection()->map(fn (Organizacion $o): array => [
                'id' => $o->id,
                'nombre' => $o->nombre,
                'tipo' => $o->tipo,
                'sector_organizacion_id' => $o->sector_organizacion_id,
                'sector_nombre' => $o->sectorOrganizacion?->nombre,
                'representante_id' => $o->representante_id,
                'representante_nombre' => $o->representante?->nombreCompleto(),
                'calle' => $o->calle,
                'num_ext' => $o->num_ext,
                'num_int' => $o->num_int,
                'colonia' => $o->colonia,
                'cp' => $o->cp,
                'estado_id' => $o->estado_id,
                'municipio_id' => $o->municipio_id,
                'localidad_id' => $o->localidad_id,
                'estado_nombre' => $o->estado?->nombre,
                'municipio_nombre' => $o->municipio?->nombre,
                'localidad_nombre' => $o->localidad?->nombre,
                'telefono' => $o->telefono,
                'celular' => $o->celular,
                'correo' => $o->correo,
            ])->values(),
            'paginacion' => [
                'total' => $organizaciones->total(),
                'currentPage' => $organizaciones->currentPage(),
                'lastPage' => $organizaciones->lastPage(),
                'prevUrl' => $organizaciones->previousPageUrl(),
                'nextUrl' => $organizaciones->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'opciones' => [
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
                'sectores' => CatSectorOrganizacion::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function store(UpsertOrganizacionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $organizacion = isset($data['id'])
            ? Organizacion::query()->findOrFail((int) $data['id'])
            : new Organizacion;

        unset($data['id']);

        if (! $organizacion->exists) {
            $organizacion->created_by = $request->user()?->id;
        }

        $organizacion->fill($data)->save();

        return back()->with('success', $organizacion->wasRecentlyCreated
            ? 'Organización creada correctamente.'
            : 'Organización actualizada correctamente.');
    }

    public function destroy(Organizacion $organizacion): RedirectResponse
    {
        $organizacion->delete();

        return back()->with('success', 'Organización eliminada correctamente.');
    }
}
