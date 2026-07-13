<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre'])]
class CatConcepto extends Model
{
    use SoftDeletes;

    protected $table = 'cat_conceptos';
}
