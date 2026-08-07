<?php

namespace App\Http\Requests\Obra;

use Illuminate\Foundation\Http\FormRequest;

class UpsertEstimacionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Los importes y porcentajes viajan como texto porque así vienen del
        // legacy (con comas, con "%", o vacíos).
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'id' => ['nullable', 'integer'],
            'num_estimacion' => $texto,
            'tipo' => ['nullable', 'integer', 'in:1,2'],
            'superintendente' => $texto,
            'residente' => $texto,
            'fecha_recepcion' => ['nullable', 'date'],
            'periodo_inicio' => ['nullable', 'date'],
            'periodo_termino' => ['nullable', 'date', 'after_or_equal:periodo_inicio'],
            'monto' => $texto,
            'calidad_obra' => $texto,
            'estatus' => $texto,
            'monto_aprobado' => $texto,
            'fecha_aprobacion' => ['nullable', 'date'],
            'num_factura' => $texto,
            'fecha_factura' => ['nullable', 'date'],
            'importe_pagado' => $texto,
            'fecha_pago' => ['nullable', 'date'],
            'avance_fisico' => $texto,
            'avance_financiero' => $texto,
            'doc_obligatoria' => $texto,
            'completo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'periodo_termino.after_or_equal' => 'El periodo no puede terminar antes de empezar.',
        ];
    }
}
