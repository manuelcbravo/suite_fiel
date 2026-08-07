<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'titulo', 'descripcion', 'recomendaciones', 'inicio', 'fin', 'todo_el_dia', 'tipo_evento_id',
    'lugar', 'localidad_id', 'contacto', 'telefono', 'personas', 'representante',
    'asiste', 'confirmado', 'discurso', 'privado', 'created_by',
])]
class Evento extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_eventos';

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'todo_el_dia' => 'boolean',
            'asiste' => 'integer',
            'confirmado' => 'boolean',
            'discurso' => 'boolean',
            'privado' => 'boolean',
        ];
    }

    /** Quién asiste al evento (legacy `asiste`). */
    public const ASISTE = [
        1 => 'Presidente (a)',
        2 => 'Representante',
    ];

    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(CatTipoEvento::class, 'tipo_evento_id');
    }

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(CatLocalidad::class, 'localidad_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
