<?php

namespace App\Http\Requests\Gestion;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeguimientoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 1 = TURNAR · 2 = NO APROBADA · 3 = PARA RESOLVER. "No aprobada" no se
        // turna a nadie, así que dependencia, responsable e instrucción sólo son
        // obligatorios en los otros dos casos.
        $turna = $this->input('estatus') !== null && (int) $this->input('estatus') !== 2;

        return [
            'estatus' => ['required', 'integer', 'in:1,2,3'],
            'dependencia_id' => [$turna ? 'required' : 'nullable', 'integer', Rule::exists('cat_dependencias', 'id')],
            'area_id' => ['nullable', 'integer', Rule::exists('cat_areas', 'id')],
            'responsable' => [$turna ? 'required' : 'nullable', 'string', 'max:255'],
            'instruccion' => [$turna ? 'required' : 'nullable', 'string'],
            'comentario' => ['nullable', 'string'],
            'fecha_comp' => ['nullable', 'string', 'max:255'],
            'localidad_resp_id' => ['nullable', 'integer', Rule::exists('cat_localidades', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estatus.required' => 'Selecciona un estatus.',
            'dependencia_id.required' => 'Selecciona la dependencia a la que se turna.',
            'responsable.required' => 'Captura el nombre del responsable.',
            'instruccion.required' => 'Captura la instrucción.',
        ];
    }
}
