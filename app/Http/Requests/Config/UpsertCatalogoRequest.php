<?php

namespace App\Http\Requests\Config;

use App\Support\CatalogoRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertCatalogoRequest extends FormRequest
{
    /**
     * Definición del catálogo apuntado por el parámetro de ruta {catalogo}.
     *
     * @return array{label: string, model: class-string, campos: list<array<string, mixed>>}
     */
    public function catalogo(): array
    {
        $definicion = CatalogoRegistry::find((string) $this->route('catalogo'));

        abort_if($definicion === null, 404);

        return $definicion;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $catalogo = $this->catalogo();
        $tabla = (new $catalogo['model'])->getTable();

        $rules = [
            'id' => ['nullable', 'integer', Rule::exists($tabla, 'id')],
        ];

        foreach ($catalogo['campos'] as $campo) {
            $reglas = [$campo['required'] ? 'required' : 'nullable'];

            if (($campo['type'] ?? 'text') === 'select') {
                $tablaOpciones = (new $campo['options'])->getTable();
                $reglas[] = 'integer';
                $reglas[] = Rule::exists($tablaOpciones, 'id');
            } else {
                $reglas[] = 'string';
                $reglas[] = 'max:255';
            }

            $rules[$campo['name']] = $reglas;
        }

        return $rules;
    }
}
