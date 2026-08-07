<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Subeje del Plan Estatal de Desarrollo.
 */
#[Fillable(['nombre', 'eje_id'])]
class CatPedSubeje extends Model
{
    use SoftDeletes;

    protected $table = 'cat_ped_subejes';

    public function eje(): BelongsTo
    {
        return $this->belongsTo(CatPedEje::class, 'eje_id');
    }
}
