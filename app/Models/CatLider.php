<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tipo de liderazgo del beneficiario (SOCIAL, SINDICAL, POLÍTICO…).
 */
#[Fillable(['nombre'])]
class CatLider extends Model
{
    use SoftDeletes;

    protected $table = 'cat_lideres';
}
