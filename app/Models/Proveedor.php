<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nombre', 'rfc', 'rep_legal', 'representante_id', 'especialidad', 'tipo', 'calificacion', 'num_prov_gob',
    'calle', 'num_ext', 'num_int', 'colonia', 'cp', 'estado_id', 'municipio_id', 'localidad_id',
    'telefono', 'celular', 'correo', 'foto', 'created_by',
])]
class Proveedor extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_proveedores';

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
