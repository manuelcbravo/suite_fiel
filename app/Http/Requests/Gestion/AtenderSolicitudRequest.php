<?php

namespace App\Http\Requests\Gestion;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtenderSolicitudRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'decision' => ['required', 'in:temporal,rapida,resolver'],
            'respuesta' => ['nullable', 'string'],
            'avance' => ['nullable', 'integer', 'between:0,100'],
            'apoyo' => $texto,
            'cantidad' => $texto,
            'unidad_medida_id' => ['nullable', 'integer', Rule::exists('cat_unidades_medida', 'id')],
            'monto' => $texto,
            'num_bene' => $texto,
            'concepto_id' => ['nullable', 'integer', Rule::exists('cat_conceptos', 'id')],
            'origen_recurso_id' => ['nullable', 'integer', Rule::exists('cat_origenes_recurso', 'id')],
            'rubros' => ['array'],
            'rubros.*' => ['integer', Rule::exists('cat_rubros', 'id')],
            'sectores' => ['array'],
            'sectores.*' => ['integer', Rule::exists('cat_sectores', 'id')],
            'estado_resp_id' => ['nullable', 'integer', Rule::exists('cat_estados', 'id')],
            'municipio_resp_id' => ['nullable', 'integer', Rule::exists('cat_municipios', 'id')],
            'localidad_resp_id' => ['nullable', 'integer', Rule::exists('cat_localidades', 'id')],
            'folio_resp' => $texto,
            'fecha_resp' => $texto,
        ];
    }
}
