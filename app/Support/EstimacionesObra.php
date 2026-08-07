<?php

namespace App\Support;

/**
 * Catálogos y lista de papeles de una estimación de obra.
 *
 * En el legacy vivían como `<option>` y `<input type="checkbox">` dentro de
 * `obra_estimacion.php`; la documentación obligatoria se guarda como los ids
 * separados por coma en `doc_obligatoria`.
 */
class EstimacionesObra
{
    /** @var array<string, array<int, string>> */
    public const CATALOGOS = [
        // `obra_estimacion.php` graba 1 y `obra_est_extra.php` graba 2.
        'tipo' => [
            1 => 'Normal',
            2 => 'Extraordinaria',
        ],
        'estatus' => [
            1 => 'AUTORIZADA',
            2 => 'NO AUTORIZADA',
        ],
        'calidad_obra' => [
            1 => 'BUENA',
            2 => 'REGULAR',
            3 => 'MALA',
        ],
    ];

    /** @var array<int, string> */
    public const DOCUMENTOS = [
        1 => 'Oficio de entrega por la empresa',
        2 => 'Números generadores de obra',
        3 => 'Croquis de los trabajos realizados',
        4 => 'Memoria fotográfica del proceso constructivo',
        5 => 'Factura con requisitos fiscales',
        6 => 'Notas de bitácora',
        7 => 'Pruebas de laboratorio',
        8 => 'Carátula de estimación',
        9 => 'Firma de estimación por superintendente (Empresa)',
    ];
}
