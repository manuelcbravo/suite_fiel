<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre'])]
class CatSegSector extends Model
{
    use SoftDeletes;

    protected $table = 'cat_seg_sectores';
}
