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
        return [
            'dependencia_id' => ['required', 'integer', Rule::exists('cat_dependencias', 'id')],
            'area_id' => ['nullable', 'integer', Rule::exists('cat_areas', 'id')],
            'instruccion' => ['nullable', 'string'],
            'comentario' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dependencia_id.required' => 'Selecciona la dependencia a la que se turna.',
        ];
    }
}
