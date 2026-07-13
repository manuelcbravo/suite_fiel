<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\StoreSeguimientoRequest;
use App\Models\CatArea;
use App\Models\Seguimiento;
use App\Models\Solicitud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeguimientoController extends Controller
{
    /**
     * Turna la solicitud a una dependencia/área (nuevo seguimiento).
     */
    public function store(StoreSeguimientoRequest $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validated();

        $solicitud->seguimientos()->create([
            'dependencia_id' => $data['dependencia_id'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'instruccion' => $data['instruccion'] ?? null,
            'comentario' => $data['comentario'] ?? null,
            'estatus' => 1, // Turnada
            'created_by' => $request->user()?->id,
        ]);

        // Refleja el turnado en la solicitud.
        if ($solicitud->status === 0) {
            $solicitud->update(['status' => 1]);
        }

        return back()->with('success', 'Solicitud turnada correctamente.');
    }

    /**
     * Registra la respuesta de un seguimiento turnado.
     */
    public function responder(Request $request, Seguimiento $seguimiento): RedirectResponse
    {
        $data = $request->validate([
            'respuesta' => ['required', 'string'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'avance' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $seguimiento->update([
            'respuesta' => $data['respuesta'],
            'responsable' => $data['responsable'] ?? $seguimiento->responsable,
            'avance' => $data['avance'] ?? $seguimiento->avance,
            'estatus' => 3, // Resuelta
            'fecha_respuesta' => now(),
        ]);

        return back()->with('success', 'Respuesta registrada.');
    }

    /**
     * Reasigna la dirección (área) de un turnado, dentro de la dependencia a la
     * que ya fue turnado (replica el flujo legacy `drccn`).
     */
    public function reasignar(Request $request, Seguimiento $seguimiento): RedirectResponse
    {
        $data = $request->validate([
            'area_id' => ['required', 'integer', Rule::exists('cat_areas', 'id')],
        ]);

        // La dirección debe pertenecer a la dependencia turnada.
        $area = CatArea::query()->find($data['area_id']);
        if ($seguimiento->dependencia_id !== null && $area && $area->dependencia_id !== $seguimiento->dependencia_id) {
            return back()->withErrors(['area_id' => 'La dirección no pertenece a la dependencia turnada.']);
        }

        $seguimiento->update(['area_id' => $data['area_id']]);

        return back()->with('success', 'Dirección de la solicitud actualizada.');
    }

    public function destroy(Seguimiento $seguimiento): RedirectResponse
    {
        $seguimiento->delete();

        return back()->with('success', 'Seguimiento eliminado.');
    }
}
