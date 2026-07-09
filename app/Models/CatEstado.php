<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'siglas'])]
class CatEstado extends Model
{
    protected $table = 'cat_estados';

    public function municipios(): HasMany
    {
        return $this->hasMany(CatMunicipio::class, 'estado_id');
    }

    public function localidades(): HasMany
    {
        return $this->hasMany(CatLocalidad::class, 'estado_id');
    }
}
