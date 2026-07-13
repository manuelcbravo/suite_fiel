<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['invitacion_id', 'correos', 'mensaje', 'enviado_en'])]
class InvitacionCorreo extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_invitacion_correos';

    protected function casts(): array
    {
        return [
            'enviado_en' => 'datetime',
        ];
    }

    public function invitacion(): BelongsTo
    {
        return $this->belongsTo(Invitacion::class, 'invitacion_id');
    }
}
