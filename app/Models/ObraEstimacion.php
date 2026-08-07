<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Estimacion de avance con su tramite de pago.
 */
#[Fillable(['obra_id', 'num_estimacion', 'tipo', 'superintendente', 'residente', 'fecha_recepcion', 'periodo_inicio', 'periodo_termino', 'monto', 'calidad_obra', 'estatus', 'monto_aprobado', 'fecha_aprobacion', 'num_factura', 'fecha_factura', 'importe_pagado', 'fecha_pago', 'avance_fisico', 'avance_financiero', 'doc_obligatoria', 'completo'])]
class ObraEstimacion extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_obra_estimaciones';

    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date',
            'periodo_inicio' => 'date',
            'periodo_termino' => 'date',
            'fecha_aprobacion' => 'date',
            'fecha_factura' => 'date',
            'fecha_pago' => 'date',
            'completo' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
