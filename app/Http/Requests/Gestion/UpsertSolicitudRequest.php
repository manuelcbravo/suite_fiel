<?php

namespace App\Http\Requests\Gestion;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSolicitudRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'id' => ['nullable', 'integer', 'exists:tbl_solicitudes,id'],
            'solicitante_tipo' => ['nullable', 'in:beneficiario,organizacion'],
            'beneficiario_id' => ['nullable', 'integer', 'exists:tbl_beneficiarios,id'],
            'organizacion_id' => ['nullable', 'integer', 'exists:tbl_organizaciones,id'],
            'folio' => ['nullable', 'string', 'max:300'],
            'folio_sistema' => ['nullable', 'string', 'max:300'],
            'solicitud' => ['nullable', 'string'],
            'apoyo' => $texto,
            'desc_bene' => ['nullable', 'string'],
            'cantidad' => $texto,
            'monto' => $texto,
            'num_bene' => $texto,
            'concepto_id' => ['nullable', 'integer', Rule::exists('cat_conceptos', 'id')],
            'procedencia_id' => ['nullable', 'integer', Rule::exists('cat_origenes_solicitud', 'id')],
            'origen' => $texto,
            'status' => ['required', 'integer', 'between:0,6'],
            'prioridad' => ['nullable', 'integer', 'between:0,5'],
            'tipo' => ['nullable', 'integer'],
            'control_administrativo' => ['boolean'],
            'fecha_recepcion' => ['nullable', 'date'],
            'estado_resp_id' => ['nullable', 'integer', Rule::exists('cat_estados', 'id')],
            'municipio_resp_id' => ['nullable', 'integer', Rule::exists('cat_municipios', 'id')],
            'localidad_resp_id' => ['nullable', 'integer', Rule::exists('cat_localidades', 'id')],
            'rubros' => ['array'],
            'rubros.*' => ['integer', Rule::exists('cat_rubros', 'id')],
            'sectores' => ['array'],
            'sectores.*' => ['integer', Rule::exists('cat_sectores', 'id')],
        ];
    }
}
