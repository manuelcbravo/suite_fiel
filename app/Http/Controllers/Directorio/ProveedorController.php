<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Directorio\UpsertProveedorRequest;
use App\Models\CatEstado;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $proveedores = Proveedor::query()
            ->with(['estado:id,nombre', 'municipio:id,nombre', 'localidad:id,nombre'])
            ->when($busqueda !== '', fn (Builder $q) => $q->where(fn (Builder $sub) => $sub
                ->whereLike('nombre', "%{$busqueda}%")
                ->orWhereLike('rfc', "%{$busqueda}%")
                ->orWhereLike('especialidad', "%{$busqueda}%")))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('directorio/proveedores/index', [
            'proveedores' => $proveedores->getCollection()->map(fn (Proveedor $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'rfc' => $p->rfc,
                'rep_legal' => $p->rep_legal,
                'especialidad' => $p->especialidad,
                'tipo' => $p->tipo,
                'calificacion' => $p->calificacion,
                'num_prov_gob' => $p->num_prov_gob,
                'calle' => $p->calle,
                'num_ext' => $p->num_ext,
                'num_int' => $p->num_int,
                'colonia' => $p->colonia,
                'cp' => $p->cp,
                'estado_id' => $p->estado_id,
                'municipio_id' => $p->municipio_id,
                'localidad_id' => $p->localidad_id,
                'estado_nombre' => $p->estado?->nombre,
                'municipio_nombre' => $p->municipio?->nombre,
                'localidad_nombre' => $p->localidad?->nombre,
                'telefono' => $p->telefono,
                'celular' => $p->celular,
                'correo' => $p->correo,
            ])->values(),
            'paginacion' => [
                'total' => $proveedores->total(),
                'currentPage' => $proveedores->currentPage(),
                'lastPage' => $proveedores->lastPage(),
                'prevUrl' => $proveedores->previousPageUrl(),
                'nextUrl' => $proveedores->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'opciones' => [
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function store(UpsertProveedorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $proveedor = isset($data['id'])
            ? Proveedor::query()->findOrFail((int) $data['id'])
            : new Proveedor;

        unset($data['id']);

        if (! $proveedor->exists) {
            $proveedor->created_by = $request->user()?->id;
        }

        $proveedor->fill($data)->save();

        return back()->with('success', $proveedor->wasRecentlyCreated
            ? 'Proveedor creado correctamente.'
            : 'Proveedor actualizado correctamente.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->delete();

        return back()->with('success', 'Proveedor eliminado correctamente.');
    }
}
