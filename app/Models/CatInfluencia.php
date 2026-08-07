<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Alcance de su influencia (SECCIONAL, MUNICIPAL, ESTATAL…).
 */
#[Fillable(['nombre'])]
class CatInfluencia extends Model
{
    use SoftDeletes;

    protected $table = 'cat_influencias';
}
