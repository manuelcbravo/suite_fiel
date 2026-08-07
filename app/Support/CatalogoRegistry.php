<?php

namespace App\Support;

use App\Models\CatAccion;
use App\Models\CatAntagonico;
use App\Models\CatArea;
use App\Models\CatConcepto;
use App\Models\CatDependencia;
use App\Models\CatEje;
use App\Models\CatEstadoCivil;
use App\Models\CatFiliacion;
use App\Models\CatInfluencia;
use App\Models\CatLider;
use App\Models\CatNivelViolencia;
use App\Models\CatOcupacion;
use App\Models\CatOrigenDenuncia;
use App\Models\CatOrigenRecurso;
use App\Models\CatOrigenSolicitud;
use App\Models\CatPedEje;
use App\Models\CatPedSubeje;
use App\Models\CatProfesion;
use App\Models\CatRubro;
use App\Models\CatSector;
use App\Models\CatSectorOrganizacion;
use App\Models\CatSegSector;
use App\Models\CatSubeje;
use App\Models\CatTipoEvento;
use App\Models\CatTipoIncidencia;
use App\Models\CatUnidadMedida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro central de los catálogos simples administrables desde la pantalla
 * "Configuración › Catálogos" (una pestaña por catálogo).
 *
 * Cada definición describe su modelo y los campos editables; esto alimenta
 * tanto la validación (UpsertCatalogoRequest) como la UI (payload de Inertia),
 * evitando duplicar un controlador/petición por cada catálogo.
 *
 * Los catálogos geográficos (estados/municipios/localidades) NO se incluyen:
 * son referencia INEGI de solo lectura.
 */
class CatalogoRegistry
{
    /**
     * @return array<string, array{label: string, model: class-string<Model>, campos: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        $texto = fn (string $name, string $label): array => [
            'name' => $name, 'label' => $label, 'type' => 'text', 'required' => true,
        ];

        return [
            'sectores' => [
                'label' => 'Sectores de población',
                'model' => CatSector::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'filiaciones' => [
                'label' => 'Filiaciones partidistas',
                'model' => CatFiliacion::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'antagonicos' => [
                'label' => 'Antagónicos',
                'model' => CatAntagonico::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'lideres' => [
                'label' => 'Tipos de liderazgo',
                'model' => CatLider::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'influencias' => [
                'label' => 'Grados de influencia',
                'model' => CatInfluencia::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'sectores_organizacion' => [
                'label' => 'Tipos de organización',
                'model' => CatSectorOrganizacion::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'ocupaciones' => [
                'label' => 'Ocupaciones',
                'model' => CatOcupacion::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'profesiones' => [
                'label' => 'Profesiones',
                'model' => CatProfesion::class,
                'campos' => [
                    $texto('nombre', 'Nombre'),
                    ['name' => 'abreviatura', 'label' => 'Abreviatura', 'type' => 'text', 'required' => false],
                ],
            ],
            'estados_civiles' => [
                'label' => 'Estados civiles',
                'model' => CatEstadoCivil::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'unidades_medida' => [
                'label' => 'Unidades de medida',
                'model' => CatUnidadMedida::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'origenes_solicitud' => [
                'label' => 'Orígenes de solicitud',
                'model' => CatOrigenSolicitud::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'origenes_recurso' => [
                'label' => 'Orígenes de recurso',
                'model' => CatOrigenRecurso::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'dependencias' => [
                'label' => 'Dependencias',
                'model' => CatDependencia::class,
                'campos' => [
                    $texto('nombre', 'Nombre'),
                    ['name' => 'responsable', 'label' => 'Responsable', 'type' => 'text', 'required' => false],
                ],
            ],
            'areas' => [
                'label' => 'Áreas',
                'model' => CatArea::class,
                'campos' => [
                    $texto('nombre', 'Nombre'),
                    ['name' => 'dependencia_id', 'label' => 'Dependencia', 'type' => 'select', 'required' => false, 'options' => CatDependencia::class],
                ],
            ],
            'ejes' => [
                'label' => 'Ejes (PMD)',
                'model' => CatEje::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'subejes' => [
                'label' => 'Subejes (PMD)',
                'model' => CatSubeje::class,
                'campos' => [
                    $texto('nombre', 'Nombre'),
                    ['name' => 'eje_id', 'label' => 'Eje', 'type' => 'select', 'required' => false, 'options' => CatEje::class],
                ],
            ],
            'ped_ejes' => [
                'label' => 'Ejes (PED)',
                'model' => CatPedEje::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'ped_subejes' => [
                'label' => 'Subejes (PED)',
                'model' => CatPedSubeje::class,
                'campos' => [
                    $texto('nombre', 'Nombre'),
                    ['name' => 'eje_id', 'label' => 'Eje', 'type' => 'select', 'required' => false, 'options' => CatPedEje::class],
                ],
            ],
            'rubros' => [
                'label' => 'Rubros',
                'model' => CatRubro::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'conceptos' => [
                'label' => 'Conceptos',
                'model' => CatConcepto::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'acciones' => [
                'label' => 'Acciones',
                'model' => CatAccion::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'tipos_evento' => [
                'label' => 'Tipos de evento (agenda)',
                'model' => CatTipoEvento::class,
                'campos' => [
                    $texto('nombre', 'Nombre'),
                    ['name' => 'color', 'label' => 'Color (hex)', 'type' => 'text', 'required' => false],
                ],
            ],
            'seg_sectores' => [
                'label' => 'Sectores de seguridad',
                'model' => CatSegSector::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'origenes_denuncia' => [
                'label' => 'Orígenes de denuncia',
                'model' => CatOrigenDenuncia::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'tipos_incidencia' => [
                'label' => 'Tipos de incidencia',
                'model' => CatTipoIncidencia::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
            'niveles_violencia' => [
                'label' => 'Niveles de violencia',
                'model' => CatNivelViolencia::class,
                'campos' => [$texto('nombre', 'Nombre')],
            ],
        ];
    }

    /**
     * Devuelve la definición de un catálogo o aborta con 404 si no existe.
     *
     * @return array{label: string, model: class-string<Model>, campos: list<array<string, mixed>>}
     */
    public static function find(string $clave): ?array
    {
        return self::all()[$clave] ?? null;
    }

    /**
     * Cuántos registros vivos apuntan a un elemento del catálogo.
     *
     * Se descubre recorriendo las llaves foráneas declaradas en el esquema, en
     * vez de mantener a mano una lista de tablas que se desincronizaría con
     * cada módulo nuevo. Sólo se llama al eliminar, que es una operación rara.
     */
    public static function usos(string $clave, int $id): int
    {
        $def = self::find($clave);

        if ($def === null) {
            return 0;
        }

        /** @var Model $instancia */
        $instancia = new $def['model'];
        $tabla = $instancia->getTable();
        $total = 0;

        foreach (Schema::getTables() as $otra) {
            $nombre = $otra['name'];

            foreach (Schema::getForeignKeys($nombre) as $fk) {
                if (($fk['foreign_table'] ?? null) !== $tabla || ($fk['columns'] ?? []) === []) {
                    continue;
                }

                $columna = $fk['columns'][0];

                $consulta = DB::table($nombre)->where($columna, $id);

                // Un expediente ya borrado no cuenta como uso.
                if (Schema::hasColumn($nombre, 'deleted_at')) {
                    $consulta->whereNull('deleted_at');
                }

                $total += $consulta->count();
            }
        }

        return $total;
    }
}
