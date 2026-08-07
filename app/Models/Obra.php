<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Obra pública. El expediente avanza por siete etapas y cada una tiene su
 * acuse de "terminada" (`*_completo`), igual que en el legacy.
 */
#[Fillable([
    'solicitud_id', 'num_obra', 'nombre', 'unidad_medida_id', 'concepto_id', 'sector_id',
    'dependencia', 'prop_financ', 'monto_inv', 'num_benef', 'descripcion', 'metas',
    'calle_num', 'localidad_id', 'referencia_mapa', 'latitud', 'longitud', 'col_beneficiados',
    'pmd_eje_id', 'pmd_subeje_id', 'ped_eje_id', 'ped_subeje_id',
    'analisis_problematica', 'analisis_sinobra', 'alternativa_solucion',
    'asp_tecnicos', 'asp_ambientales', 'asp_legales', 'asp_sociales',
    'rentab_social', 'impacto_social', 'descrip_renta_impac', 'factibilidad', 'conclusion',
    'ficha_completo', 'expediente_completo', 'autorizacion_completo', 'adjudicacion_completo',
    'ejecucion_completo', 'conclusion_completo', 'entrega_completo', 'created_by',
])]
class Obra extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_obras';

    /** Las siete etapas, en el orden en que avanza el expediente. */
    public const ETAPAS = [
        'ficha' => 'Ficha técnica',
        'expediente' => 'Expediente',
        'autorizacion' => 'Autorización',
        'adjudicacion' => 'Adjudicación',
        'ejecucion' => 'Ejecución',
        'conclusion' => 'Conclusión',
        'entrega' => 'Entrega-recepción',
    ];

    protected function casts(): array
    {
        return [
            'ficha_completo' => 'boolean',
            'expediente_completo' => 'boolean',
            'autorizacion_completo' => 'boolean',
            'adjudicacion_completo' => 'boolean',
            'ejecucion_completo' => 'boolean',
            'conclusion_completo' => 'boolean',
            'entrega_completo' => 'boolean',
        ];
    }

    /** La etapa más avanzada que ya quedó cerrada; null si no hay ninguna. */
    public function etapaActual(): ?string
    {
        $ultima = null;

        foreach (array_keys(self::ETAPAS) as $etapa) {
            if ($this->{"{$etapa}_completo"}) {
                $ultima = $etapa;
            }
        }

        return $ultima;
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    /** Unidad de medida de la meta; el legacy la guarda como `tipo_obra_id`. */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(CatUnidadMedida::class, 'unidad_medida_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(CatConcepto::class, 'concepto_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(CatSector::class, 'sector_id');
    }

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(CatLocalidad::class, 'localidad_id');
    }

    public function pmdEje(): BelongsTo
    {
        return $this->belongsTo(CatEje::class, 'pmd_eje_id');
    }

    public function pmdSubeje(): BelongsTo
    {
        return $this->belongsTo(CatSubeje::class, 'pmd_subeje_id');
    }

    public function pedEje(): BelongsTo
    {
        return $this->belongsTo(CatPedEje::class, 'ped_eje_id');
    }

    public function pedSubeje(): BelongsTo
    {
        return $this->belongsTo(CatPedSubeje::class, 'ped_subeje_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(ObraDocumento::class, 'obra_id');
    }

    public function autorizacion(): HasOne
    {
        return $this->hasOne(ObraAutorizacion::class, 'obra_id');
    }

    public function adjudicacion(): HasOne
    {
        return $this->hasOne(ObraAdjudicacion::class, 'obra_id');
    }

    public function ejecucion(): HasOne
    {
        return $this->hasOne(ObraEjecucion::class, 'obra_id');
    }

    public function cierre(): HasOne
    {
        return $this->hasOne(ObraCierre::class, 'obra_id');
    }

    public function componentes(): HasMany
    {
        return $this->hasMany(ObraComponente::class, 'obra_id');
    }

    public function beneficios(): HasMany
    {
        return $this->hasMany(ObraBeneficio::class, 'obra_id');
    }

    public function costos(): HasMany
    {
        return $this->hasMany(ObraCosto::class, 'obra_id');
    }

    public function estimaciones(): HasMany
    {
        return $this->hasMany(ObraEstimacion::class, 'obra_id');
    }
}
