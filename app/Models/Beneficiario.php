<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nombre', 'paterno', 'materno', 'alias', 'curp', 'genero', 'nacimiento', 'tipo', 'estado_civil_id',
    'calle', 'num_ext', 'num_int', 'colonia', 'cp', 'estado_id', 'municipio_id', 'localidad_id',
    'telefono', 'celular', 'celular2', 'correo', 'correo2', 'facebook', 'twitter',
    'empresa', 'puesto', 'tel_empresa', 'ocupacion_id', 'profesion_id', 'ocupacion_texto',
    'sector_id', 'grupo', 'vinculo_municipal', 'vinculo_estatal', 'vinculo_federal',
    'asist_nombre', 'asist_movil', 'asist_correo',
    'conyuge_nombre', 'conyuge_movil', 'conyuge_nacimiento',
    'foto', 'estatus', 'created_by',
])]
class Beneficiario extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_beneficiarios';

    protected function casts(): array
    {
        return [
            'nacimiento' => 'date',
            'conyuge_nacimiento' => 'date',
        ];
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->nombre} {$this->paterno} {$this->materno}");
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

    public function ocupacion(): BelongsTo
    {
        return $this->belongsTo(CatOcupacion::class, 'ocupacion_id');
    }

    public function profesion(): BelongsTo
    {
        return $this->belongsTo(CatProfesion::class, 'profesion_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(CatSector::class, 'sector_id');
    }

    public function estadoCivil(): BelongsTo
    {
        return $this->belongsTo(CatEstadoCivil::class, 'estado_civil_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organizacionesRepresentadas(): HasMany
    {
        return $this->hasMany(Organizacion::class, 'representante_id');
    }

    public function comentarios(): MorphMany
    {
        return $this->morphMany(Comentario::class, 'comentable');
    }
}
