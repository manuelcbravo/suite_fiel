<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'eje_id', 'estado_id', 'municipio_id'])]
class CatSubeje extends Model
{
    use SoftDeletes;

    protected $table = 'cat_subejes';

    public function eje(): BelongsTo
    {
        return $this->belongsTo(CatEje::class, 'eje_id');
    }
}
