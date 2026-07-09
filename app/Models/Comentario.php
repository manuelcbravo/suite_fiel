<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comentario polimórfico: aplica a beneficiarios, organizaciones y proveedores
 * (consolida las tablas legacy tbl_comentario_bene/org/prov).
 */
#[Fillable(['comentario', 'tipo', 'quien', 'created_by'])]
class Comentario extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_comentarios';

    public function comentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
