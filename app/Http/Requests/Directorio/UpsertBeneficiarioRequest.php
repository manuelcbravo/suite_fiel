<?php

namespace App\Http\Requests\Directorio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertBeneficiarioRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'id' => ['nullable', 'integer', 'exists:tbl_beneficiarios,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'paterno' => $texto,
            'materno' => $texto,
            'alias' => $texto,
            'curp' => ['nullable', 'string', 'max:18'],
            'genero' => ['nullable', 'integer', 'in:1,2'],
            'nacimiento' => ['nullable', 'date'],
            'tipo' => ['nullable', 'integer', 'in:1,2'],
            'estado_civil_id' => ['nullable', 'integer', Rule::exists('cat_estados_civiles', 'id')],
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
            'celular2' => $texto,
            'correo' => $texto,
            'correo2' => $texto,
            'facebook' => $texto,
            'twitter' => $texto,
            'empresa' => $texto,
            'puesto' => $texto,
            'tel_empresa' => $texto,
            'ocupacion_id' => ['nullable', 'integer', Rule::exists('cat_ocupaciones', 'id')],
            'profesion_id' => ['nullable', 'integer', Rule::exists('cat_profesiones', 'id')],
            'sector_id' => ['nullable', 'integer', Rule::exists('cat_sectores', 'id')],
            'grupo' => $texto,
            'vinculo_municipal' => $texto,
            'vinculo_estatal' => $texto,
            'vinculo_federal' => $texto,
            'asist_nombre' => $texto,
            'asist_movil' => $texto,
            'asist_correo' => $texto,
            'conyuge_nombre' => $texto,
            'conyuge_movil' => $texto,
            'conyuge_nacimiento' => ['nullable', 'date'],
            'estatus' => ['nullable', 'integer'],
            'foto' => ['nullable', 'string'],
        ];
    }
}
