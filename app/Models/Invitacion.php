<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'titulo', 'destinatario', 'inicio', 'fin', 'todo_el_dia', 'tipo_evento_id', 'evento_id',
    'lugar', 'descripcion', 'recomendaciones', 'contacto', 'telefono',
    'fecha_recepcion', 'confirmado', 'atendida', 'comentario', 'created_by',
])]
class Invitacion extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_invitaciones';

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'fecha_recepcion' => 'datetime',
            'todo_el_dia' => 'boolean',
            'confirmado' => 'boolean',
            'atendida' => 'boolean',
        ];
    }

    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(CatTipoEvento::class, 'tipo_evento_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function correos(): HasMany
    {
        return $this->hasMany(InvitacionCorreo::class, 'invitacion_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
