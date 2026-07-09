<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tipo de organización (EMPRESARIAL, SOCIAL, POLÍTICO, RELIGIOSO).
 */
#[Fillable(['nombre'])]
class CatSectorOrganizacion extends Model
{
    use SoftDeletes;

    protected $table = 'cat_sectores_organizacion';
}
