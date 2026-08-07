<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Licitacion, fallo, contratista y contrato.
 */
#[Fillable(['obra_id', 'num_licitacion', 'num_oficio_contrato', 'fecha_oficio_contrato', 'convocatoria', 'num_convocatoria', 'fecha_convocatoria', 'medios_convocatoria', 'costo_bases', 'empresa_id', 'nom_empresa', 'rfc_empresa', 'dir_empresa', 'rep_empresa', 'reg_empresa', 'num_fianza', 'monto_fianza', 'num_cumplimiento', 'monto_cumplimiento', 'num_contrato', 'fecha_contrato', 'monto_contrato', 'monto_contrato_original', 'fecha_inicio_obra', 'fecha_termino_obra', 'fecha_inicio_original', 'fecha_termino_original', 'num_convenio', 'fecha_convenio'])]
class ObraAdjudicacion extends Model
{
    protected $table = 'tbl_obra_adjudicacion';

    protected function casts(): array
    {
        return [
            'fecha_oficio_contrato' => 'date',
            'fecha_convocatoria' => 'date',
            'fecha_contrato' => 'date',
            'fecha_inicio_obra' => 'date',
            'fecha_termino_obra' => 'date',
            'fecha_inicio_original' => 'date',
            'fecha_termino_original' => 'date',
            'fecha_convenio' => 'date',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    /** La contratista, que es un proveedor del directorio. */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'empresa_id');
    }
}
