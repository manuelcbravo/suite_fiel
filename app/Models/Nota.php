<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nota', 'fecha', 'evento_id', 'created_by'])]
class Nota extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_notas';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function pendientes(): HasMany
    {
        return $this->hasMany(Notita::class, 'nota_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
