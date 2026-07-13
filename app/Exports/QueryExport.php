<?php

namespace App\Exports;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export genérico: recibe una consulta ya filtrada, los encabezados y una
 * función de mapeo por fila. Lo usan los reportes (agenda, directorio,
 * gestión) para exportar exactamente lo que se ve, respetando los filtros.
 */
class QueryExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Closure(): Builder  $consulta
     * @param  list<string>  $encabezados
     * @param  Closure(mixed): array<mixed>  $mapa
     */
    public function __construct(
        private Closure $consulta,
        private array $encabezados,
        private Closure $mapa,
    ) {}

    public function query(): Builder
    {
        return ($this->consulta)();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->encabezados;
    }

    /**
     * @return array<mixed>
     */
    public function map($row): array
    {
        return ($this->mapa)($row);
    }
}
