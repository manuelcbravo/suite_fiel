<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Filiación partidista del beneficiario (PAN, PRI, MORENA…).
 */
#[Fillable(['nombre'])]
class CatFiliacion extends Model
{
    use SoftDeletes;

    protected $table = 'cat_filiaciones';
}
