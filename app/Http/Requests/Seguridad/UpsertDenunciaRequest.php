<?php

namespace App\Http\Requests\Seguridad;

use App\Models\Denuncia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertDenunciaRequest extends FormRequest
{
    /** Formatos que el capturista suele teclear a mano. */
    private const FORMATOS_FECHA = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y'];

    /**
     * Las fechas se guardan siempre como `Y-m-d`. La columna es texto (viene
     * así del legacy) y el listado filtra el rango comparando cadenas, así que
     * con `15/01/2026` conviviendo con `2026-03-01` el rango no significaba
     * nada. Normalizando a la entrada la comparación vuelve a ser correcta.
     */
    protected function prepareForValidation(): void
    {
        foreach (['fecha_denuncia', 'fecha_atencion'] as $campo) {
            $valor = $this->input($campo);

            if (is_string($valor) && trim($valor) !== '') {
                $this->merge([$campo => $this->normalizar(trim($valor))]);
            }
        }
    }

    private function normalizar(string $valor): string
    {
        foreach (self::FORMATOS_FECHA as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat('!'.$formato, $valor);

            if ($fecha !== false) {
                return $fecha->format('Y-m-d');
            }
        }

        // No se reconoce: se deja tal cual para que la validación lo rechace.
        return $valor;
    }

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
            'fecha_denuncia' => ['nullable', 'date_format:Y-m-d'],
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
            'fecha_atencion' => ['nullable', 'date_format:Y-m-d'],
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

    /**
     * El alta/edición también muestra los acuses de etapa, así que aquí rigen
     * las mismas reglas que en `atender()` y `concluir()`. Sin esto se podía
     * concluir una denuncia sin toma de conocimiento con sólo marcar la
     * casilla, saltándose la guarda del flujo.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $denuncia = $this->filled('id')
                ? Denuncia::query()->find((int) $this->input('id'))
                : null;

            $conAtencion = $this->boolean('con_atencion');
            $conTermino = $this->boolean('con_termino');

            if ($conAtencion && ! $this->tomaDeConocimientoCompleta($denuncia)) {
                $validator->errors()->add(
                    'con_atencion',
                    'Captura quién atiende, la fecha, la descripción y el presunto delito antes de marcar la toma de conocimiento.',
                );
            }

            if ($conTermino && ! ($conAtencion || (bool) $denuncia?->con_atencion)) {
                $validator->errors()->add(
                    'con_termino',
                    'La denuncia no se puede concluir sin la toma de conocimiento.',
                );
            }
        });
    }

    /**
     * Los cuatro datos que `atender()` exige, ya sea porque vienen en esta
     * petición o porque la denuncia ya los tenía guardados.
     */
    private function tomaDeConocimientoCompleta(?Denuncia $denuncia): bool
    {
        foreach (['atendido_por', 'fecha_atencion', 'descripcion_situacion', 'tipo_incidencia_id'] as $campo) {
            if (! $this->filled($campo) && ! filled($denuncia?->{$campo})) {
                return false;
            }
        }

        return true;
    }
}
