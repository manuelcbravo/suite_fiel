<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Arranque, bitacora, avances y pagos de la obra en marcha.
 */
#[Fillable(['obra_id', 'fecha_inicio_real', 'fecha_termino_real', 'responsable', 'fecha_orden_inicio', 'fecha_termino_orden', 'fecha_entrega_inmueble', 'fecha_apertura_bitacora', 'tipo_bitacora', 'residente', 'superintendente', 'observaciones', 'anticipo', 'num_factura', 'monto_factura', 'fecha_factura', 'monto_pagado', 'fecha_pagado'])]
class ObraEjecucion extends Model
{
    protected $table = 'tbl_obra_ejecucion';

    protected function casts(): array
    {
        return [
            'fecha_inicio_real' => 'date',
            'fecha_termino_real' => 'date',
            'fecha_orden_inicio' => 'date',
            'fecha_termino_orden' => 'date',
            'fecha_entrega_inmueble' => 'date',
            'fecha_apertura_bitacora' => 'date',
            'fecha_factura' => 'date',
            'fecha_pagado' => 'date',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
