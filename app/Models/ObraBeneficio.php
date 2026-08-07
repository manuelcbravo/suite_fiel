<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beneficio esperado de la obra, para la evaluacion social.
 */
#[Fillable(['obra_id', 'tipo', 'descripcion', 'cuantificacion', 'valoracion', 'periodicidad'])]
class ObraBeneficio extends Model
{
    protected $table = 'tbl_obra_beneficios';

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
