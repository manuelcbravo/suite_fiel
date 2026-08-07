<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seguridad\UpsertDetenidoRequest;
use App\Models\CatEstado;
use App\Models\CatEstadoCivil;
use App\Models\CatOcupacion;
use App\Models\Denuncia;
use App\Models\Detenido;
use App\Support\FichaPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DetenidoController extends Controller
{
    public function index(Request $request): Response
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $detenidos = Detenido::query()
            ->with(['denuncia:id,denuncia', 'ocupacion:id,nombre', 'estado:id,nombre'])
            ->when($busqueda !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                ->whereLike('nombre', "%{$busqueda}%")
                ->orWhereLike('paterno', "%{$busqueda}%")
                ->orWhereLike('alias', "%{$busqueda}%")
                ->orWhereLike('motivo_retencion', "%{$busqueda}%")))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('seguridad/detenidos/index', [
            'detenidos' => $detenidos->getCollection()->map($this->serializar(...))->values(),
            'paginacion' => [
                'total' => $detenidos->total(),
                'currentPage' => $detenidos->currentPage(),
                'lastPage' => $detenidos->lastPage(),
                'prevUrl' => $detenidos->previousPageUrl(),
                'nextUrl' => $detenidos->nextPageUrl(),
                'busqueda' => $busqueda,
            ],
            'opciones' => [
                'ocupaciones' => CatOcupacion::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estadosCiviles' => CatEstadoCivil::query()->orderBy('nombre')->get(['id', 'nombre']),
                'estados' => CatEstado::query()->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function store(UpsertDetenidoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $detenido = isset($data['id'])
            ? Detenido::query()->findOrFail((int) $data['id'])
            : new Detenido;

        unset($data['id']);

        if (! $detenido->exists) {
            $detenido->created_by = $request->user()?->id;
        }

        $detenido->fill($data)->save();

        return back()->with('success', $detenido->wasRecentlyCreated
            ? 'Detenido registrado correctamente.'
            : 'Detenido actualizado correctamente.');
    }

    public function destroy(Detenido $detenido): RedirectResponse
    {
        $detenido->delete();

        return back()->with('success', 'Detenido eliminado correctamente.');
    }

    /**
     * Búsqueda ligera de denuncias para vincular un detenido.
     */
    public function buscarDenuncias(Request $request): JsonResponse
    {
        $busqueda = $request->string('busqueda')->trim()->toString();

        $items = Denuncia::query()
            ->when($busqueda !== '', fn (Builder $q) => $q->whereLike('denuncia', "%{$busqueda}%"))
            ->latest('id')
            ->limit(20)
            ->get(['id', 'denuncia'])
            ->map(fn ($d): array => [
                'id' => $d->id,
                'nombre' => '#'.$d->id.' — '.Str::limit($d->denuncia ?? 'Sin descripción', 50),
            ]);

        return response()->json($items);
    }

    /**
     * Ficha imprimible del detenido (legacy `ficha_dtnd.php`).
     */
    public function pdf(Request $request, Detenido $detenido): SymfonyResponse
    {
        $detenido->load(['ocupacion', 'estadoCivil', 'municipio', 'denuncia']);

        $pdf = new FichaPdf(
            $detenido->nombreCompleto(),
            '#'.$detenido->id,
            'Ficha del detenido',
            $request->user()?->name,
        );

        $pdf->seccion('Identidad')->datos([
            'Alias / Apodo' => $detenido->alias,
            'Edad' => $detenido->edad ? $detenido->edad.' años' : null,
            'Fecha de nacimiento' => $detenido->fecha_nac?->toDateString(),
            // En este módulo el legacy usa 2 = hombre y 1 = mujer.
            'Sexo' => match ($detenido->sexo) {
                2 => 'Hombre',
                1 => 'Mujer',
                default => null,
            },
            'Nacionalidad' => $detenido->nacionalidad,
            'Lugar de nacimiento' => $detenido->lugar_nac,
            'Ocupación / Oficio' => $detenido->ocupacion?->nombre,
            'Estado civil' => $detenido->estadoCivil?->nombre,
            'Padre' => $detenido->padre_nombre,
            'Madre' => $detenido->madre_nombre,
        ]);

        $pdf->seccion('Contacto')->datos([
            'Dirección' => $detenido->direccion,
            'Municipio' => $detenido->municipio?->nombre,
            'Celular' => $detenido->celular,
            'Teléfono' => $detenido->telefono,
        ]);

        $pdf->seccion('Retención')->datos([
            'Lugar' => $detenido->lugar_retencion,
            'Fecha' => $detenido->fecha_retencion,
            'Denuncia' => $detenido->denuncia_id ? '#'.$detenido->denuncia_id : null,
        ]);

        $pdf->lista([
            'Motivo de la retención' => $detenido->motivo_retencion,
            'Descripción gráfica' => $detenido->descripcion_grafica,
            'Observaciones' => $detenido->observaciones,
        ]);

        return response($pdf->salida(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="detenido-'.$detenido->id.'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Detenido $d): array
    {
        return [
            'id' => $d->id,
            'nombre_completo' => $d->nombreCompleto(),
            // Igual que en Directorio: sin foto no se pide la imagen.
            'tiene_foto' => filled($d->foto),
            'nombre' => $d->nombre,
            'paterno' => $d->paterno,
            'materno' => $d->materno,
            'alias' => $d->alias,
            'edad' => $d->edad,
            'fecha_nac' => $d->fecha_nac?->toDateString(),
            'sexo' => $d->sexo,
            'nacionalidad' => $d->nacionalidad,
            'lugar_nac' => $d->lugar_nac,
            'ocupacion_id' => $d->ocupacion_id,
            'ocupacion_nombre' => $d->ocupacion?->nombre,
            'estado_civil_id' => $d->estado_civil_id,
            'estado_id' => $d->estado_id,
            'municipio_id' => $d->municipio_id,
            'direccion' => $d->direccion,
            'celular' => $d->celular,
            'telefono' => $d->telefono,
            'lugar_retencion' => $d->lugar_retencion,
            'fecha_retencion' => $d->fecha_retencion,
            'padre_nombre' => $d->padre_nombre,
            'madre_nombre' => $d->madre_nombre,
            'motivo_retencion' => $d->motivo_retencion,
            'observaciones' => $d->observaciones,
            'denuncia_id' => $d->denuncia_id,
            'denuncia_label' => $d->denuncia ? ('#'.$d->denuncia->id) : null,
        ];
    }
}
