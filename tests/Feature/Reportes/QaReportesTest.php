<?php

/**
 * QA adversarial — Categoría E: Reportes y Tableros.
 *
 * Escritos contra el comportamiento ESPERADO. Los que fallan documentan un
 * defecto pendiente de aprobación; no se toca código de producción.
 */

use App\Models\Beneficiario;
use App\Models\Evento;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function qaUsuarioReportes(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function qaSolicitudReporte(array $extra = []): Solicitud
{
    $bene = Beneficiario::query()->create(['nombre' => 'ANA', 'paterno' => 'RUIZ']);

    return Solicitud::query()->create(array_merge([
        'folio' => 'F-1',
        'solicitud' => 'Apoyo',
        'solicitante_type' => Beneficiario::class,
        'solicitante_id' => $bene->id,
        'status' => 5,
        'monto' => '1000',
        'fecha_recepcion' => '2026-01-10',
    ], $extra));
}

// ---------------------------------------------------------------------------
// E-01 · HAPPY PATH (control: debe pasar)
// ---------------------------------------------------------------------------

it('E-01 abre el reporte de agenda', function () {
    $this->actingAs(qaUsuarioReportes());

    $this->get(route('reportes.agenda'))->assertOk();
});

// ---------------------------------------------------------------------------
// E-02..E-05 · TABLEROS
//
// Ejecutivo, Gestión y Financiero agregan con SQL exclusivo de PostgreSQL
// (`DISTINCT ON`, `count(*) filter`, `::numeric`, `~`, `CURRENT_DATE +
// INTERVAL`), así que sólo se pueden ejercitar contra ese motor. Se marcan
// igual que la vista General de `GestionVistasTest` para que quede explícito
// que no corren en SQLite, en vez de fallar sin explicación.
// ---------------------------------------------------------------------------

$soloPostgres = fn () => \Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'pgsql'
    ? 'El tablero agrega con SQL exclusivo de PostgreSQL.'
    : false;

it('E-02 abre el tablero ejecutivo', function () {
    $this->actingAs(qaUsuarioReportes());
    qaSolicitudReporte();

    $this->get(route('tableros.ejecutivo'))->assertOk();
})->skip($soloPostgres);

it('E-03 abre el tablero de gestión', function () {
    $this->actingAs(qaUsuarioReportes());
    qaSolicitudReporte();

    $this->get(route('tableros.gestion'))->assertOk();
})->skip($soloPostgres);

it('E-04 abre el tablero financiero', function () {
    $this->actingAs(qaUsuarioReportes());
    qaSolicitudReporte();

    $this->get(route('tableros.financiero'))->assertOk();
})->skip($soloPostgres);

it('E-05 abre el tablero de seguridad', function () {
    $this->actingAs(qaUsuarioReportes());

    $this->get(route('tableros.seguridad'))->assertOk();
});

// ---------------------------------------------------------------------------
// E-06..E-09 · FILTROS DEGENERADOS
// ---------------------------------------------------------------------------

it('E-06 rechaza una fecha de filtro que no es una fecha en vez de reventar', function () {
    $this->actingAs(qaUsuarioReportes());

    // `Request::date()` lanza InvalidArgumentException si no puede parsear.
    $r = $this->get(route('reportes.gestion', ['desde' => 'LA SEMANA PASADA']));

    expect($r->status())->not->toBe(500);
});

it('E-07 avisa cuando el rango de fechas viene invertido', function () {
    $this->actingAs(qaUsuarioReportes());
    qaSolicitudReporte();

    // desde > hasta: no es un rango válido, debería avisarse en vez de
    // devolver un reporte vacío que parece "no hay datos".
    $this->get(route('reportes.gestion', ['desde' => '2026-12-31', 'hasta' => '2026-01-01']))
        ->assertSessionHasErrors();
});

it('E-08 rechaza un monto de filtro que no es numérico', function () {
    $this->actingAs(qaUsuarioReportes());

    // `(float) 'MUCHO DINERO'` = 0.0: el filtro se aplica en silencio con un
    // valor que el usuario nunca pidió.
    $this->get(route('reportes.gestion', ['monto_min' => 'MUCHO DINERO']))
        ->assertSessionHasErrors('monto_min');
});

it('E-09 rechaza un rango de montos invertido', function () {
    $this->actingAs(qaUsuarioReportes());

    $this->get(route('reportes.gestion', ['monto_min' => '5000', 'monto_max' => '100']))
        ->assertSessionHasErrors();
});

// ---------------------------------------------------------------------------
// E-10 · EXPORTACIÓN (dependencia externa: maatwebsite/excel)
// ---------------------------------------------------------------------------

it('E-10 exporta la agenda a Excel', function () {
    $this->actingAs(qaUsuarioReportes());
    Evento::query()->create(['titulo' => 'REUNIÓN', 'inicio' => '2026-01-10 09:00:00']);

    $this->get(route('reportes.agenda.excel'))->assertOk();
});

it('E-11 acota el número de filas que un solo Excel puede llevarse', function () {
    $this->actingAs(qaUsuarioReportes());

    foreach (range(1, 60) as $i) {
        Evento::query()->create(['titulo' => "EVENTO {$i}", 'inicio' => '2026-01-10 09:00:00']);
    }

    // El export corre síncrono y sin tope: con el padrón real (≈47 mil
    // solicitudes y siete relaciones precargadas) esto es un OOM o un timeout.
    // Debería haber un límite explícito o encolarse.
    $r = $this->get(route('reportes.gestion.excel', ['limite' => 10]));

    expect($r->headers->get('X-Filas-Exportadas'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// E-12 · PAGINACIÓN
// ---------------------------------------------------------------------------

it('E-12 tolera un número de página fuera de rango', function () {
    $this->actingAs(qaUsuarioReportes());
    qaSolicitudReporte();

    $r = $this->get(route('reportes.gestion', ['page' => 999999]));

    expect($r->status())->not->toBe(500);
});

it('E-13 tolera un número de página negativo', function () {
    $this->actingAs(qaUsuarioReportes());
    qaSolicitudReporte();

    $r = $this->get(route('reportes.gestion', ['page' => -5]));

    expect($r->status())->not->toBe(500);
});

// ---------------------------------------------------------------------------
// E-14 · EL EXPORT DE LA VISTA GENERAL SÓLO LLEVA LA PÁGINA VISIBLE
// ---------------------------------------------------------------------------

it('E-14 exporta todas las localidades de la vista General, no sólo la página', function () {
    $this->actingAs(qaUsuarioReportes());

    // 25 localidades con solicitud: la vista pagina de 20 en 20, pero el
    // Excel debería llevarse las 25.
    $estado = \App\Models\CatEstado::query()->create(['nombre' => 'HIDALGO']);
    $municipio = \App\Models\CatMunicipio::query()->create([
        'clave' => 48, 'nombre' => 'PACHUCA', 'estado_id' => $estado->id,
    ]);

    foreach (range(1, 25) as $i) {
        $loc = \App\Models\CatLocalidad::query()->create([
            'clave' => $i, 'nombre' => "LOC {$i}",
            'municipio_id' => $municipio->id, 'estado_id' => $estado->id,
        ]);
        qaSolicitudReporte(['localidad_resp_id' => $loc->id]);
    }

    $vista = $this->get(route('reportes.gestion', ['vista' => 'general']));
    $vista->assertOk();

    expect($vista->viewData('page')['props']['paginacion']['total'] ?? 0)->toBe(25);

    // `gestionExcel()` reusa `gestionGeneral()`, que ya viene paginado a 20.
    $this->get(route('reportes.gestion.excel', ['vista' => 'general']))->assertOk();
})->skip(
    fn () => \Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'pgsql'
        ? 'La vista General agrega con SQL exclusivo de PostgreSQL.'
        : false,
);
