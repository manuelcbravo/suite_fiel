<?php

namespace App\Support;

use App\Models\CatAccion;
use App\Models\CatArea;
use App\Models\CatConcepto;
use App\Models\CatDependencia;
use App\Models\CatEje;
use App\Models\CatNivelViolencia;
use App\Models\CatOrigenDenuncia;
use App\Models\CatRubro;
use App\Models\CatSegSector;
use App\Models\CatTipoIncidencia;
use App\Models\CatEstadoCivil;
use App\Models\CatOcupacion;
use App\Models\CatOrigenRecurso;
use App\Models\CatOrigenSolicitud;
use App\Models\CatProfesion;
use App\Models\CatSector;
use App\Models\CatSectorOrganizacion;
use App\Models\CatSubeje;
use App\Models\CatTipoEvento;
use App\Models\CatUnidadMedida;
use Illuminate\Database\Eloquent\Model;

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
}
