<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'solicitud_id', 'dependencia_id', 'area_id', 'estatus', 'instruccion', 'comentario',
    'respuesta', 'responsable', 'fecha_respuesta', 'avance', 'estatus_resp', 'respuesta_de_id', 'created_by',
])]
class Seguimiento extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_seguimientos';

    /** Etiquetas de `estatus` del turnado (legacy). */
    public const ESTATUS = [
        1 => 'Turnada',
        2 => 'No aprobada',
        3 => 'Resuelta',
    ];

    protected function casts(): array
    {
        return [
            'fecha_respuesta' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(CatDependencia::class, 'dependencia_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(CatArea::class, 'area_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estatusLabel(): string
    {
        return self::ESTATUS[$this->estatus] ?? (string) $this->estatus;
    }
}
