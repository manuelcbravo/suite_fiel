<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nombre', 'tipo', 'sector_organizacion_id', 'representante_id',
    'calle', 'num_ext', 'num_int', 'colonia', 'cp', 'estado_id', 'municipio_id', 'localidad_id',
    'telefono', 'celular', 'correo', 'foto', 'created_by',
])]
class Organizacion extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_organizaciones';

    public function sectorOrganizacion(): BelongsTo
    {
        return $this->belongsTo(CatSectorOrganizacion::class, 'sector_organizacion_id');
    }

    public function representante(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class, 'representante_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(CatEstado::class, 'estado_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(CatMunicipio::class, 'municipio_id');
    }

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(CatLocalidad::class, 'localidad_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'comentable');
    }
}
