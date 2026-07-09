<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Unidad de medida de apoyos (BULTO, TONELADA, PIEZA, DINERO…).
 */
#[Fillable(['nombre'])]
class CatUnidadMedida extends Model
{
    protected $table = 'cat_unidades_medida';
}
