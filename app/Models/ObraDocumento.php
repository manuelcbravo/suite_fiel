<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un papel del expediente de obra: si esta o no, y que se anoto.
 */
#[Fillable(['obra_id', 'etapa', 'clave', 'presente', 'observacion'])]
class ObraDocumento extends Model
{
    protected $table = 'tbl_obra_documentos';

    protected function casts(): array
    {
        return [
            'presente' => 'boolean',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
