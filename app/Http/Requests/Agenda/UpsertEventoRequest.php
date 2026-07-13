<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertEventoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'titulo' => ['required', 'string', 'max:255'],
            'tipo_evento_id' => ['nullable', 'integer', Rule::exists('cat_tipos_evento', 'id')],
            'inicio' => ['required', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:inicio'],
            'todo_el_dia' => ['boolean'],
            'lugar' => $texto,
            'descripcion' => ['nullable', 'string'],
            'recomendaciones' => ['nullable', 'string'],
            'contacto' => $texto,
            'telefono' => $texto,
            'representante' => $texto,
            'personas' => $texto,
            'asiste' => ['boolean'],
            'confirmado' => ['boolean'],
            'discurso' => ['boolean'],
            'privado' => ['boolean'],
        ];
    }
}
