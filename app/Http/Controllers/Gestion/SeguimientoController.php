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
     * Da seguimiento a la solicitud (legacy `seguimiento_add.php`). El estatus
     * elegido decide el flujo y se copia tal cual a la solicitud:
     *   1 = TURNAR · 2 = NO APROBADA · 3 = PARA RESOLVER
     */
    public function store(StoreSeguimientoRequest $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validated();
        $estatus = (int) $data['estatus'];
        $noAprobada = $estatus === 2;

        $solicitud->seguimientos()->create([
            // "No aprobada" no se turna a nadie: sólo lleva comentario.
            'dependencia_id' => $noAprobada ? null : ($data['dependencia_id'] ?? null),
            'area_id' => $noAprobada ? null : ($data['area_id'] ?? null),
            'responsable' => $noAprobada ? null : ($data['responsable'] ?? null),
            'instruccion' => $noAprobada ? null : ($data['instruccion'] ?? null),
            'comentario' => $data['comentario'] ?? null,
            'estatus' => $estatus,
            'created_by' => $request->user()?->id,
        ]);

        $solicitud->update([
            'status' => $estatus,
            'fecha_comp' => $data['fecha_comp'] ?? null,
            'localidad_resp_id' => $data['localidad_resp_id'] ?? $solicitud->localidad_resp_id,
        ]);

        return back()->with('success', match ($estatus) {
            2 => 'Solicitud marcada como no aprobada.',
            3 => 'Solicitud turnada para resolver.',
            default => 'Solicitud turnada correctamente.',
        });
    }

    /**
     * Marca si ya se notificó al ciudadano. En el legacy sólo aplica a las
     * solicitudes no aprobadas y se guarda al instante (`notificar.php`).
     */
    public function notificar(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $data = $request->validate([
            'notificacion' => ['required', 'boolean'],
        ]);

        if ($solicitud->status !== 2) {
            return back()->withErrors([
                'notificacion' => 'Sólo se puede notificar una solicitud no aprobada.',
            ]);
        }

        $solicitud->update(['notificacion' => $data['notificacion']]);

        return back()->with('success', $data['notificacion']
            ? 'Ciudadano notificado.'
            : 'Notificación pendiente.');
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
            'estatus_resp' => 4, // Respuesta del área
            'fecha_respuesta' => now(),
            'respuesta_de_id' => $request->user()?->id,
        ]);

        $seguimiento->solicitud?->update(['status' => 4]);

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
