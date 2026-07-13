<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Verificación / seguimiento de satisfacción de una solicitud atendida
 * (legacy `tbl_verificar`).
 */
#[Fillable(['solicitud_id', 'fecha', 'atendido', 'satisfecho', 'comentario', 'created_by'])]
class Verificacion extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_verificaciones';

    /** Etiquetas de satisfacción (legacy). */
    public const SATISFACCION = [1 => 'Sí', 2 => 'Poco', 3 => 'Nada'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
