<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Oficios, metas y estructura financiera con que se autoriza la obra.
 */
#[Fillable(['obra_id', 'num_oficio_envio', 'fecha_oficio_envio', 'recep_oficio_envio', 'num_oficio_aut', 'fecha_oficio_aut', 'recep_oficio_aut', 'num_obra_oficio_aut', 'mod_ejecucion', 'tipo_contratacion', 'nombre_obra_aut', 'inicio_obra_aut', 'termino_obra_aut', 'descrip_obra_aut', 'finan_prog', 'finan_subprog', 'finan_costo', 'finan_estruc', 'metas_unidad', 'metas_cantidad', 'metas_benef', 'ejercicio_fiscal', 'ramo', 'fondo', 'aporte_fed', 'aporte_est', 'aporte_mun', 'aporte_bene', 'aporte_otr', 'presupuesto', 'anio_prog_anual'])]
class ObraAutorizacion extends Model
{
    protected $table = 'tbl_obra_autorizacion';

    protected function casts(): array
    {
        return [
            'fecha_oficio_envio' => 'date',
            'fecha_oficio_aut' => 'date',
            'inicio_obra_aut' => 'date',
            'termino_obra_aut' => 'date',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }
}
