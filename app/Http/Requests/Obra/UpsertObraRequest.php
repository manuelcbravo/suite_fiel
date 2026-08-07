<?php

namespace App\Http\Requests\Obra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertObraRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Los importes y cantidades viajan como texto porque así vienen del
        // legacy y se capturan con formatos libres.
        $texto = ['nullable', 'string', 'max:255'];
        $largo = ['nullable', 'string'];

        return [
            'id' => ['nullable', 'integer', Rule::exists('tbl_obras', 'id')],
            'solicitud_id' => ['nullable', 'integer', Rule::exists('tbl_solicitudes', 'id')],
            'num_obra' => $texto,
            'nombre' => $largo,
            'unidad_medida_id' => ['nullable', 'integer', Rule::exists('cat_unidades_medida', 'id')],
            'concepto_id' => ['nullable', 'integer', Rule::exists('cat_conceptos', 'id')],
            'sector_id' => ['nullable', 'integer', Rule::exists('cat_sectores', 'id')],
            'dependencia' => $texto,
            'prop_financ' => $texto,
            'monto_inv' => $texto,
            'num_benef' => $texto,
            'descripcion' => $largo,
            'metas' => $largo,

            'calle_num' => $texto,
            'localidad_id' => ['nullable', 'integer', Rule::exists('cat_localidades', 'id')],
            'referencia_mapa' => $largo,
            'latitud' => $texto,
            'longitud' => $texto,
            'col_beneficiados' => $largo,

            'pmd_eje_id' => ['nullable', 'integer', Rule::exists('cat_ejes', 'id')],
            'pmd_subeje_id' => ['nullable', 'integer', Rule::exists('cat_subejes', 'id')],
            'ped_eje_id' => ['nullable', 'integer', Rule::exists('cat_ped_ejes', 'id')],
            'ped_subeje_id' => ['nullable', 'integer', Rule::exists('cat_ped_subejes', 'id')],

            'analisis_problematica' => $largo,
            'analisis_sinobra' => $largo,
            'alternativa_solucion' => $largo,
            'asp_tecnicos' => $largo,
            'asp_ambientales' => $largo,
            'asp_legales' => $largo,
            'asp_sociales' => $largo,
            'rentab_social' => $largo,
            'impacto_social' => $largo,
            'descrip_renta_impac' => $largo,
            'factibilidad' => $largo,
            'conclusion' => $largo,

            'ficha_completo' => ['boolean'],
        ];
    }
}
