<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export de filas ya calculadas, para los reportes que agregan en la base y
 * no tienen una consulta fila-por-registro que exportar (la vista General de
 * Gestión resume por localidad).
 */
class ColeccionExport implements FromArray, WithHeadings
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<array<mixed>>  $filas
     */
    public function __construct(
        private array $encabezados,
        private array $filas,
    ) {}

    /** @return list<array<mixed>> */
    public function array(): array
    {
        return $this->filas;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->encabezados;
    }
}
