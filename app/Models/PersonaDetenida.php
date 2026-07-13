<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'paterno', 'materno', 'sexo', 'fecha_nac', 'nacionalidad'])]
class PersonaDetenida extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_personas_detenidas';

    protected function casts(): array
    {
        return ['fecha_nac' => 'date'];
    }

    public function detenciones(): HasMany
    {
        return $this->hasMany(Detenido::class, 'persona_id');
    }
}
