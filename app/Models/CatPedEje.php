<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eje del Plan Estatal de Desarrollo.
 */
#[Fillable(['nombre'])]
class CatPedEje extends Model
{
    use SoftDeletes;

    protected $table = 'cat_ped_ejes';
}
