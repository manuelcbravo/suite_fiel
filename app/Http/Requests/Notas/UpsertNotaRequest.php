<?php

namespace App\Http\Requests\Notas;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertNotaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:tbl_notas,id'],
            'nota' => ['required', 'string', 'max:5000'],
            'fecha' => ['nullable', 'date'],
            'evento_id' => ['nullable', 'integer', Rule::exists('tbl_eventos', 'id')],
        ];
    }
}
