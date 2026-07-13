<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'folio', 'folio_sistema', 'solicitud', 'apoyo', 'desc_bene', 'cantidad', 'monto', 'num_bene', 'bene_final',
    'solicitante_type', 'solicitante_id', 'concepto_id', 'procedencia_id', 'origen',
    'status', 'prioridad', 'tipo', 'control_administrativo', 'fecha_recepcion', 'fecha_comp',
    'estado_resp_id', 'municipio_resp_id', 'localidad_resp_id', 'folio_resp', 'fecha_resp',
    'imagen', 'latitud', 'longitud', 'created_by',
])]
class Solicitud extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_solicitudes';

    /** Etiquetas de `status` (legacy). */
    public const ESTATUS = [
        0 => 'Capturada',
        1 => 'Turnada',
        2 => 'No aprobada',
        3 => 'Para resolver',
        4 => 'Respuesta de área',
        5 => 'Atendida',
        6 => 'Atención rápida',
    ];

    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date',
            'control_administrativo' => 'boolean',
        ];
    }

    public function solicitante(): MorphTo
    {
        return $this->morphTo();
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(CatConcepto::class, 'concepto_id');
    }

    public function procedencia(): BelongsTo
    {
        return $this->belongsTo(CatOrigenSolicitud::class, 'procedencia_id');
    }

    /** Unidad de medida del apoyo (columna legacy `tipo`). */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(CatUnidadMedida::class, 'tipo');
    }

    /** Origen del recurso (columna legacy `origen`, guarda el id). */
    public function origenRecurso(): BelongsTo
    {
        return $this->belongsTo(CatOrigenRecurso::class, 'origen');
    }

    public function estadoResp(): BelongsTo
    {
        return $this->belongsTo(CatEstado::class, 'estado_resp_id');
    }

    public function municipioResp(): BelongsTo
    {
        return $this->belongsTo(CatMunicipio::class, 'municipio_resp_id');
    }

    public function localidadResp(): BelongsTo
    {
        return $this->belongsTo(CatLocalidad::class, 'localidad_resp_id');
    }

    public function rubros(): BelongsToMany
    {
        return $this->belongsToMany(CatRubro::class, 'tbl_solicitud_rubro', 'solicitud_id', 'rubro_id');
    }

    public function sectores(): BelongsToMany
    {
        return $this->belongsToMany(CatSector::class, 'tbl_solicitud_sector', 'solicitud_id', 'sector_id');
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(Seguimiento::class, 'solicitud_id');
    }

    public function verificaciones(): HasMany
    {
        return $this->hasMany(Verificacion::class, 'solicitud_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estatusLabel(): string
    {
        return self::ESTATUS[$this->status] ?? (string) $this->status;
    }
}
