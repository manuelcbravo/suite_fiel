<?php

namespace App\Http\Controllers\Directorio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Directorio\UpsertProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;

class ProveedorController extends Controller
{
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
