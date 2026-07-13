<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre'])]
class CatOrigenDenuncia extends Model
{
    protected $table = 'cat_origenes_denuncia';
}
