<?php

namespace App\Http\Controllers\Notas;

use App\Http\Controllers\Controller;
use App\Models\Nota;
use App\Models\Notita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotitaController extends Controller
{
    public function store(Request $request, Nota $nota): RedirectResponse
    {
        $data = $request->validate([
            'texto' => ['required', 'string', 'max:255'],
        ]);

        $nota->pendientes()->create([
            'texto' => $data['texto'],
            'realizado' => false,
        ]);

        return back()->with('success', 'Pendiente agregado.');
    }

    /** Editar el texto del pendiente, como el `notas_cambiar.php` del legacy. */
    public function update(Request $request, Notita $notita): RedirectResponse
    {
        $data = $request->validate([
            'texto' => ['required', 'string', 'max:255'],
        ]);

        $notita->update(['texto' => $data['texto']]);

        return back()->with('success', 'Pendiente actualizado.');
    }

    public function toggle(Notita $notita): RedirectResponse
    {
        $notita->update(['realizado' => ! $notita->realizado]);

        return back();
    }

    public function destroy(Notita $notita): RedirectResponse
    {
        $notita->delete();

        return back()->with('success', 'Pendiente eliminado.');
    }
}
