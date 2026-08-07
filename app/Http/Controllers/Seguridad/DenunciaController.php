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
use App\Models\Detenido;
use App\Support\FichaPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DenunciaController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();
        $localidadId = $request->integer('localidad_id') ?: null;
        $estatus = $request->input('estatus') !== null && $request->input('estatus') !== ''
            ? (int) $request->input('estatus')
            : null;
        $desde = $request->string('ini')->toString();
        $hasta = $request->string('fin')->toString();

        // El mismo filtro alimenta la tabla y el mapa, así que se arma aparte.
        $filtrado = fn (): Builder => Denuncia::query()
            ->when($busqueda !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->whereLike('denuncia', "%{$busqueda}%")
                ->orWhereLike('denunciante_nombre', "%{$busqueda}%")
                ->orWhereLike('atendido_por', "%{$busqueda}%")))
            ->when($localidadId, fn (Builder $q) => $q->where('localidad_id', $localidadId))
            // 1 recepción · 2 atención · 3 término, como el filtro del legacy.
            ->when($estatus === 1, fn (Builder $q) => $q->where('con_atencion', false)->where('con_termino', false))
            ->when($estatus === 2, fn (Builder $q) => $q->where('con_atencion', true)->where('con_termino', false))
            ->when($estatus === 3, fn (Builder $q) => $q->where('con_termino', true))
            ->when($desde !== '', fn (Builder $q) => $q->where('fecha_denuncia', '>=', $desde))
            ->when($hasta !== '', fn (Builder $q) => $q->where('fecha_denuncia', '<=', $hasta));

        $denuncias = $filtrado()
            ->with(['tipoIncidencia:id,nombre', 'segSector:id,nombre', 'municipio:id,nombre', 'localidad:id,nombre'])
            ->withCount('detenidos')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('seguridad/denuncias/index', [
            'denuncias' => $denuncias->getCollection()->map($this->serializar(...))->values(),
            'filtros' => [
                'busqueda' => $busqueda,
                'localidad_id' => $localidadId,
                'estatus' => $estatus,
                'ini' => $desde,
                'fin' => $hasta,
            ],
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

    /**
     * Toma de conocimiento (legacy `dnncs_tncn.php`): el oficial que atiende
     * describe la situación y la clasifica. Deja la denuncia "En atención".
     */
    public function atender(Request $request, Denuncia $denuncia): RedirectResponse
    {
        $data = $request->validate([
            'atendido_por' => ['required', 'string', 'max:255'],
            'fecha_atencion' => ['required', 'date'],
            'hora_atencion' => ['nullable', 'string', 'max:10'],
            'descripcion_situacion' => ['required', 'string'],
            'tipo_incidencia_id' => ['required', 'integer', Rule::exists('cat_tipos_incidencia', 'id')],
            'nivel_violencia_id' => ['nullable', 'integer', Rule::exists('cat_niveles_violencia', 'id')],
            'seg_sector_id' => ['nullable', 'integer', Rule::exists('cat_seg_sectores', 'id')],
            'estado_id' => ['nullable', 'integer', Rule::exists('cat_estados', 'id')],
            'municipio_id' => ['nullable', 'integer', Rule::exists('cat_municipios', 'id')],
            'localidad_id' => ['nullable', 'integer', Rule::exists('cat_localidades', 'id')],
            // Las fija el mapa; llegan como texto igual que en el legacy.
            'latitud' => ['nullable', 'string', 'max:50'],
            'longitud' => ['nullable', 'string', 'max:50'],
            'acciones' => ['nullable', 'string'],
        ], [
            'atendido_por.required' => 'Captura quién atiende.',
            'descripcion_situacion.required' => 'Describe la situación.',
            'tipo_incidencia_id.required' => 'Selecciona el presunto delito.',
        ]);

        $denuncia->fill([...$data, 'con_atencion' => true])->save();

        return back()->with('success', 'Toma de conocimiento registrada.');
    }

    /**
     * Término (legacy `dnncs_trmn.php`): acuerdos y conclusión. Al marcarlo,
     * la denuncia queda concluida.
     */
    public function concluir(Request $request, Denuncia $denuncia): RedirectResponse
    {
        $data = $request->validate([
            'acuerdos_convenios' => ['nullable', 'string'],
            'conclusion' => ['nullable', 'string'],
            'con_termino' => ['boolean'],
        ]);

        if (($data['con_termino'] ?? false) && ! $denuncia->con_atencion) {
            return back()->withErrors([
                'con_termino' => 'La denuncia no se puede concluir sin la toma de conocimiento.',
            ]);
        }

        $denuncia->fill($data)->save();

        return back()->with('success', ($data['con_termino'] ?? false)
            ? 'Denuncia concluida.'
            : 'Término guardado.');
    }

    /**
     * Ficha imprimible de la denuncia (el botón "PDF" del listado legacy).
     */
    public function pdf(Request $request, Denuncia $denuncia): SymfonyResponse
    {
        $denuncia->load(['tipoIncidencia', 'nivelViolencia', 'segSector', 'origenDenuncia', 'municipio', 'localidad', 'detenidos']);

        $pdf = new FichaPdf(
            'Denuncia o aviso',
            '#'.$denuncia->id,
            $denuncia->estatusLabel(),
            $request->user()?->name,
        );

        $pdf->seccion('Recepción')->datos([
            'Denunciante' => $denuncia->anonimo ? 'Anónimo' : $denuncia->denunciante(),
            'Origen' => $denuncia->origenDenuncia?->nombre,
            'Fecha' => $denuncia->fecha_denuncia,
            'Hora' => $denuncia->hora_denuncia,
            'Municipio' => $denuncia->municipio?->nombre,
            'Localidad' => $denuncia->localidad?->nombre,
        ]);

        $pdf->lista(['Reporte' => $denuncia->denuncia]);

        if ($denuncia->con_atencion) {
            $pdf->seccion('Toma de conocimiento')->datos([
                'Quién atendió' => $denuncia->atendido_por,
                'Fecha' => $denuncia->fecha_atencion,
                'Hora' => $denuncia->hora_atencion,
                'Presunto delito' => $denuncia->tipoIncidencia?->nombre,
                'Nivel de violencia' => $denuncia->nivelViolencia?->nombre,
                'Sector' => $denuncia->segSector?->nombre,
            ]);

            $pdf->lista([
                'Descripción de la situación' => $denuncia->descripcion_situacion,
                'Acciones realizadas' => $denuncia->acciones,
            ]);
        }

        if ($denuncia->con_termino) {
            $pdf->seccion('Término')->lista([
                'Acuerdos y convenios' => $denuncia->acuerdos_convenios,
                'Conclusión' => $denuncia->conclusion,
            ]);
        }

        if ($denuncia->detenidos->isNotEmpty()) {
            $pdf->seccion('Detenidos')->lista([
                'Personas' => $denuncia->detenidos
                    ->map(fn (Detenido $d): string => $d->nombreCompleto())
                    ->implode(', '),
            ]);
        }

        return $this->descarga($pdf->salida(), 'denuncia-'.$denuncia->id.'.pdf');
    }

    /** Respuesta de descarga en línea para un PDF ya armado. */
    private function descarga(string $contenido, string $nombre): SymfonyResponse
    {
        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
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
            'localidad_nombre' => $d->localidad?->nombre,
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
