<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['clave', 'nombre', 'estado_id', 'latitud', 'longitud'])]
class CatMunicipio extends Model
{
    protected $table = 'cat_municipios';

    public function estado(): BelongsTo
    {
        return $this->belongsTo(CatEstado::class, 'estado_id');
    }

    public function localidades(): HasMany
    {
        return $this->hasMany(CatLocalidad::class, 'municipio_id');
    }
}
