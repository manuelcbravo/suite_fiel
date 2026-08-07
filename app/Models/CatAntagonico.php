<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Con qué orden de gobierno tiene diferencias el beneficiario.
 */
#[Fillable(['nombre'])]
class CatAntagonico extends Model
{
    use SoftDeletes;

    protected $table = 'cat_antagonicos';
}
