<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seguridad\UpsertDenunciaRequest;
use App\Models\CatEstado;
use App\Models\CatNivelViolencia;
use App\Models\CatOrigenDenuncia;
use App\Models\CatSegSector;
use App\Models\CatTipoIncidencia;
use App\Models\Denuncia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DenunciaController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $denuncias = Denuncia::query()
            ->with(['tipoIncidencia:id,nombre', 'segSector:id,nombre', 'municipio:id,nombre', 'localidad:id,nombre'])
            ->withCount('detenidos')
            ->when($busqueda !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->whereLike('denuncia', "%{$busqueda}%")
                ->orWhereLike('denunciante_nombre', "%{$busqueda}%")
                ->orWhereLike('atendido_por', "%{$busqueda}%")))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('seguridad/denuncias/index', [
            'denuncias' => $denuncias->getCollection()->map($this->serializar(...))->values(),
            'paginacion' => [
                'total' => $denuncias->total(),
                'currentPage' => $denuncias->currentPage(),
                'lastPage' => $denuncias->lastPage(),
                'prevUrl' => $denuncias->previousPageUrl(),
                'nextUrl' => $denuncias->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'opciones' => [
                'origenes' => CatOrigenDenuncia::query()->orderBy('nombre')->get(['id', 'nombre']),
                'tiposIncidencia' => CatTipoIncidencia::query()->orderBy('nombre')->get(['id', 'nombre']),
                'nivelesViolencia' => CatNivelViolencia::query()->get(['id', 'nombre']),
                'sectores' => CatSegSector::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function store(UpsertDenunciaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $denuncia = isset($data['id'])
            ? Denuncia::query()->findOrFail((int) $data['id'])
            : new Denuncia;

        unset($data['id']);

        if (! $denuncia->exists) {
            $denuncia->created_by = $request->user()?->id;
        }

        $denuncia->fill($data)->save();

        return back()->with('success', $denuncia->wasRecentlyCreated
            ? 'Denuncia registrada correctamente.'
            : 'Denuncia actualizada correctamente.');
    }

    public function destroy(Denuncia $denuncia): RedirectResponse
    {
        $denuncia->delete();

        return back()->with('success', 'Denuncia eliminada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Denuncia $d): array
    {
        return [
            'id' => $d->id,
            'anonimo' => $d->anonimo,
            'denunciante' => $d->denunciante(),
            'denunciante_nombre' => $d->denunciante_nombre,
            'denunciante_paterno' => $d->denunciante_paterno,
            'denunciante_materno' => $d->denunciante_materno,
            'fecha_denuncia' => $d->fecha_denuncia,
            'hora_denuncia' => $d->hora_denuncia,
            'origen_denuncia_id' => $d->origen_denuncia_id,
            'denuncia' => $d->denuncia,
            'descripcion_situacion' => $d->descripcion_situacion,
            'tipo_incidencia_id' => $d->tipo_incidencia_id,
            'tipo_nombre' => $d->tipoIncidencia?->nombre,
            'nivel_violencia_id' => $d->nivel_violencia_id,
            'seg_sector_id' => $d->seg_sector_id,
            'sector_nombre' => $d->segSector?->nombre,
            'estado_id' => $d->estado_id,
            'municipio_id' => $d->municipio_id,
            'municipio_nombre' => $d->municipio?->nombre,
            'localidad_id' => $d->localidad_id,
            'latitud' => $d->latitud,
            'longitud' => $d->longitud,
            'atendido_por' => $d->atendido_por,
            'fecha_atencion' => $d->fecha_atencion,
            'hora_atencion' => $d->hora_atencion,
            'acciones' => $d->acciones,
            'acuerdos_convenios' => $d->acuerdos_convenios,
            'conclusion' => $d->conclusion,
            'asignado' => $d->asignado,
            'vehiculo' => $d->vehiculo,
            'turnado' => $d->turnado,
            'con_atencion' => $d->con_atencion,
            'con_termino' => $d->con_termino,
            'estatus_label' => $d->estatusLabel(),
            'detenidos_count' => $d->detenidos_count,
        ];
    }
}
