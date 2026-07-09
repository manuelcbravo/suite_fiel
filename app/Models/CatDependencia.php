<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'responsable'])]
class CatDependencia extends Model
{
    use SoftDeletes;

    protected $table = 'cat_dependencias';

    public function areas(): HasMany
    {
        return $this->hasMany(CatArea::class, 'dependencia_id');
    }
}
