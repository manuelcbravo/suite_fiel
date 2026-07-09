<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eje del Plan Municipal de Desarrollo.
 */
#[Fillable(['nombre', 'estado_id', 'municipio_id'])]
class CatEje extends Model
{
    use SoftDeletes;

    protected $table = 'cat_ejes';

    public function subejes(): HasMany
    {
        return $this->hasMany(CatSubeje::class, 'eje_id');
    }
}
