<?php

namespace App\Http\Requests\Invitaciones;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCorreoRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'correos' => ['required', 'string', 'max:2000'],
            'mensaje' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'correos.required' => 'Indica al menos un correo destinatario.',
        ];
    }
}
