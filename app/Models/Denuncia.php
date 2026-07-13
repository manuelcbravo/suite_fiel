<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'anonimo', 'denunciante_nombre', 'denunciante_paterno', 'denunciante_materno',
    'fecha_denuncia', 'hora_denuncia', 'origen_denuncia_id', 'denuncia', 'descripcion_situacion',
    'tipo_incidencia_id', 'nivel_violencia_id', 'seg_sector_id',
    'estado_id', 'municipio_id', 'localidad_id', 'latitud', 'longitud',
    'atendido_por', 'fecha_atencion', 'hora_atencion', 'acciones', 'acuerdos_convenios', 'conclusion',
    'asignado', 'vehiculo', 'clasificacion', 'turnado', 'con_atencion', 'con_termino', 'created_by',
])]
class Denuncia extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_denuncias';

    protected function casts(): array
    {
        return [
            'anonimo' => 'boolean',
            'con_atencion' => 'boolean',
            'con_termino' => 'boolean',
        ];
    }

    /** Etiqueta del estado del flujo. */
    public function estatusLabel(): string
    {
        if ($this->con_termino) {
            return 'Concluida';
        }
        if ($this->con_atencion) {
            return 'En atención';
        }
        if ($this->turnado > 0) {
            return 'Turnada';
        }

        return 'Recibida';
    }

    public function denunciante(): string
    {
        if ($this->anonimo) {
            return 'Anónimo';
        }

        return trim("{$this->denunciante_nombre} {$this->denunciante_paterno} {$this->denunciante_materno}") ?: 'Sin nombre';
    }

    public function origenDenuncia(): BelongsTo
    {
        return $this->belongsTo(CatOrigenDenuncia::class, 'origen_denuncia_id');
    }

    public function tipoIncidencia(): BelongsTo
    {
        return $this->belongsTo(CatTipoIncidencia::class, 'tipo_incidencia_id');
    }

    public function nivelViolencia(): BelongsTo
    {
        return $this->belongsTo(CatNivelViolencia::class, 'nivel_violencia_id');
    }

    public function segSector(): BelongsTo
    {
        return $this->belongsTo(CatSegSector::class, 'seg_sector_id');
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

    public function detenidos(): HasMany
    {
        return $this->hasMany(Detenido::class, 'denuncia_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
