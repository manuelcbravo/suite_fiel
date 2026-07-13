<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre'])]
class CatNivelViolencia extends Model
{
    protected $table = 'cat_niveles_violencia';
}
