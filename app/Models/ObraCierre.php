<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conclusion y entrega-recepcion: finiquito, actas y fianza de vicios.
 */
#[Fillable(['obra_id', 'fecha_aviso_conclusion', 'revision_trabajos', 'acta_entrega_fisica', 'fecha_acta_entrega_fisica', 'finiquito', 'observaciones_conclusion', 'fecha_fianza_vicios', 'fecha_invitacion_acto', 'invitados_acto', 'fecha_rev_fisica_doc', 'observacion_general', 'acta_entrega_recepcion', 'fecha_acta_entrega_recepcion', 'fecha_acta_extincion', 'observaciones_entrega'])]
class ObraCierre extends Model
{
    protected $table = 'tbl_obra_cierre';

    protected function casts(): array
    {
        return [
            'fecha_aviso_conclusion' => 'date',
            'fecha_acta_entrega_fisica' => 'date',
            'fecha_fianza_vicios' => 'date',
            'fecha_invitacion_acto' => 'date',
            'fecha_rev_fisica_doc' => 'date',
            'fecha_acta_entrega_recepcion' => 'date',
            'fecha_acta_extincion' => 'date',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
