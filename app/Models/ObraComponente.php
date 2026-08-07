<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Componente presupuestal de la obra.
 */
#[Fillable(['obra_id', 'componente', 'descripcion', 'cantidad', 'costo', 'total'])]
class ObraComponente extends Model
{
    protected $table = 'tbl_obra_componentes';

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
