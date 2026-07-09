<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'abreviatura'])]
class CatProfesion extends Model
{
    protected $table = 'cat_profesiones';
}
