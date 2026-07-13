<?php

namespace App\Http\Requests\Directorio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertProveedorRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'id' => ['nullable', 'integer', 'exists:tbl_proveedores,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'representante_id' => ['nullable', 'integer', Rule::exists('tbl_beneficiarios', 'id')],
            'especialidad' => $texto,
            'tipo' => ['nullable', 'integer'],
            'calificacion' => ['nullable', 'integer', 'min:0', 'max:10'],
            'num_prov_gob' => $texto,
            'calle' => $texto,
            'num_ext' => $texto,
            'num_int' => $texto,
            'colonia' => $texto,
            'cp' => ['nullable', 'string', 'max:10'],
            'estado_id' => ['nullable', 'integer', Rule::exists('cat_estados', 'id')],
            'municipio_id' => ['nullable', 'integer', Rule::exists('cat_municipios', 'id')],
            'localidad_id' => ['nullable', 'integer', Rule::exists('cat_localidades', 'id')],
            'telefono' => $texto,
            'celular' => $texto,
            'correo' => $texto,
            'foto' => ['nullable', 'string'],
        ];
    }
}
