<?php

namespace App\Http\Requests\Invitaciones;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertInvitacionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        return [
            'id' => ['nullable', 'integer', 'exists:tbl_invitaciones,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'destinatario' => $texto,
            'inicio' => ['required', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:inicio'],
            'todo_el_dia' => ['boolean'],
            'tipo_evento_id' => ['nullable', 'integer', Rule::exists('cat_tipos_evento', 'id')],
            'evento_id' => ['nullable', 'integer', Rule::exists('tbl_eventos', 'id')],
            'lugar' => $texto,
            'descripcion' => ['nullable', 'string'],
            'recomendaciones' => ['nullable', 'string'],
            'contacto' => $texto,
            'telefono' => $texto,
            'fecha_recepcion' => ['nullable', 'date'],
            'confirmado' => ['boolean'],
            'atendida' => ['boolean'],
            'comentario' => ['nullable', 'string'],
        ];
    }
}
