<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['clave', 'nombre', 'municipio_id', 'estado_id', 'tipo_localidad_id', 'cp', 'clave_ine'])]
class CatLocalidad extends Model
{
    use SoftDeletes;

    protected $table = 'cat_localidades';

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(CatMunicipio::class, 'municipio_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(CatEstado::class, 'estado_id');
    }

    public function tipoLocalidad(): BelongsTo
    {
        return $this->belongsTo(CatTipoLocalidad::class, 'tipo_localidad_id');
    }
}
