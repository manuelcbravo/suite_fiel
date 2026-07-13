<?php

namespace App\Http\Requests\Seguridad;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertDetenidoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'id' => ['nullable', 'integer', 'exists:tbl_detenidos,id'],
            'denuncia_id' => ['nullable', 'integer', Rule::exists('tbl_denuncias', 'id')],
            'nombre' => ['required', 'string', 'max:255'],
            'paterno' => $texto,
            'materno' => $texto,
            'alias' => $texto,
            'edad' => ['nullable', 'integer', 'between:0,120'],
            'fecha_nac' => ['nullable', 'date'],
            'sexo' => ['nullable', 'integer', 'in:1,2'],
            'nacionalidad' => $texto,
            'lugar_nac' => $texto,
            'ocupacion_id' => ['nullable', 'integer', Rule::exists('cat_ocupaciones', 'id')],
            'estado_civil_id' => ['nullable', 'integer', Rule::exists('cat_estados_civiles', 'id')],
            'estado_id' => ['nullable', 'integer', Rule::exists('cat_estados', 'id')],
            'municipio_id' => ['nullable', 'integer', Rule::exists('cat_municipios', 'id')],
            'direccion' => $texto,
            'celular' => $texto,
            'telefono' => $texto,
            'lugar_retencion' => $texto,
            'fecha_retencion' => $texto,
            'padre_nombre' => $texto,
            'madre_nombre' => $texto,
            'motivo_retencion' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
