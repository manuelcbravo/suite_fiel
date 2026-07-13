<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'denuncia_id', 'persona_id', 'nombre', 'paterno', 'materno', 'alias', 'edad', 'fecha_nac',
    'sexo', 'nacionalidad', 'lugar_nac', 'ocupacion_id', 'estado_civil_id', 'estado_id', 'municipio_id',
    'direccion', 'celular', 'telefono', 'lugar_retencion', 'fecha_retencion',
    'padre_nombre', 'madre_nombre', 'motivo_retencion', 'descripcion_grafica', 'observaciones',
    'foto', 'created_by',
])]
class Detenido extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_detenidos';

    protected function casts(): array
    {
        return ['fecha_nac' => 'date'];
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->nombre} {$this->paterno} {$this->materno}");
    }

    public function denuncia(): BelongsTo
    {
        return $this->belongsTo(Denuncia::class, 'denuncia_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(PersonaDetenida::class, 'persona_id');
    }

    public function ocupacion(): BelongsTo
    {
        return $this->belongsTo(CatOcupacion::class, 'ocupacion_id');
    }

    public function estadoCivil(): BelongsTo
    {
        return $this->belongsTo(CatEstadoCivil::class, 'estado_civil_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(CatEstado::class, 'estado_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(CatMunicipio::class, 'municipio_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
