<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Padrón de secciones electorales del estado: a qué municipio pertenece cada
 * sección y en qué distrito federal y local cae.
 */
#[Fillable(['seccion', 'estado_id', 'municipio_id', 'distrito_federal', 'distrito_local'])]
class CatSeccion extends Model
{
    protected $table = 'cat_secciones';

    public function estado(): BelongsTo
    {
        return $this->belongsTo(CatEstado::class, 'estado_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(CatMunicipio::class, 'municipio_id');
    }
}
