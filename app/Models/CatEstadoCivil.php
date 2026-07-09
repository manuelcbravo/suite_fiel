<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre'])]
class CatEstadoCivil extends Model
{
    protected $table = 'cat_estados_civiles';
}
