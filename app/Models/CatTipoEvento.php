<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'color'])]
class CatTipoEvento extends Model
{
    use SoftDeletes;

    protected $table = 'cat_tipos_evento';

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'tipo_evento_id');
    }
}
