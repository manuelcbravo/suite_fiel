<?php

namespace App\Http\Requests\Seguridad;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertDenunciaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'id' => ['nullable', 'integer', 'exists:tbl_denuncias,id'],
            'anonimo' => ['boolean'],
            'denunciante_nombre' => $texto,
            'denunciante_paterno' => $texto,
            'denunciante_materno' => $texto,
            'fecha_denuncia' => $texto,
            'hora_denuncia' => $texto,
            'origen_denuncia_id' => ['nullable', 'integer', Rule::exists('cat_origenes_denuncia', 'id')],
            'denuncia' => ['nullable', 'string'],
            'descripcion_situacion' => ['nullable', 'string'],
            'tipo_incidencia_id' => ['nullable', 'integer', Rule::exists('cat_tipos_incidencia', 'id')],
            'nivel_violencia_id' => ['nullable', 'integer', Rule::exists('cat_niveles_violencia', 'id')],
            'seg_sector_id' => ['nullable', 'integer', Rule::exists('cat_seg_sectores', 'id')],
            'estado_id' => ['nullable', 'integer', Rule::exists('cat_estados', 'id')],
            'municipio_id' => ['nullable', 'integer', Rule::exists('cat_municipios', 'id')],
            'localidad_id' => ['nullable', 'integer', Rule::exists('cat_localidades', 'id')],
            'latitud' => $texto,
            'longitud' => $texto,
            'atendido_por' => $texto,
            'fecha_atencion' => $texto,
            'hora_atencion' => $texto,
            'acciones' => ['nullable', 'string'],
            'acuerdos_convenios' => ['nullable', 'string'],
            'conclusion' => ['nullable', 'string'],
            'asignado' => $texto,
            'vehiculo' => $texto,
            'turnado' => ['nullable', 'integer', 'between:0,3'],
            'con_atencion' => ['boolean'],
            'con_termino' => ['boolean'],
        ];
    }
}
