<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sector de población (ADULTOS MAYORES, JÓVENES, MUJERES…).
 */
#[Fillable(['nombre'])]
class CatSector extends Model
{
    use SoftDeletes;

    protected $table = 'cat_sectores';
}
