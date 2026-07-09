<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'dependencia_id'])]
class CatArea extends Model
{
    use SoftDeletes;

    protected $table = 'cat_areas';

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(CatDependencia::class, 'dependencia_id');
    }
}
