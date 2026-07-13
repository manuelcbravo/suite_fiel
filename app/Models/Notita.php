<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pendiente tipo checklist dentro de una nota.
 */
#[Fillable(['nota_id', 'texto', 'realizado'])]
class Notita extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_notitas';

    protected function casts(): array
    {
        return [
            'realizado' => 'boolean',
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class, 'nota_id');
    }
}
