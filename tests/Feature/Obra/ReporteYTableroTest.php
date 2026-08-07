<?php

use App\Models\Beneficiario;
use App\Models\Obra;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function usuarioReporteObra(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function obraConDatos(array $atributos = []): Obra
{
    $bene = Beneficiario::query()->create(['nombre' => 'Ana', 'paterno' => 'Ruiz']);
    $solicitud = Solicitud::query()->create([
        'folio' => 'DOP-1',
        'folio_sistema' => 'DOP-1-1',
        'solicitud' => 'Drenaje',
        'solicitante_type' => Beneficiario::class,
        'solicitante_id' => $bene->id,
        'status' => 0,
    ]);

    return Obra::query()->create([
        'solicitud_id' => $solicitud->id,
        'nombre' => 'OBRA DE PRUEBA',
        ...$atributos,
    ]);
}

it('arma el reporte con las columnas por omisión', function () {
    $this->actingAs(usuarioReporteObra());

    $obra = obraConDatos(['num_obra' => 'OB-1', 'monto_inv' => '1,200,000.00']);
    $obra->adjudicacion()->create([
        'nom_empresa' => 'CONSTRUCTORA X',
        'monto_contrato' => '1,100,000.00',
    ]);

    $this->get(route('reportes.obra'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/obra')
            ->where('filas.0.nomobra', 'OBRA DE PRUEBA')
            ->where('filas.0.numobra', 'OB-1')
            ->where('filas.0.invtot', '1,200,000.00')
            ->where('filas.0.contratista', 'CONSTRUCTORA X'));
});

it('el avance del reporte sale de la última estimación que lo trae', function () {
    $this->actingAs(usuarioReporteObra());

    $obra = obraConDatos();
    $obra->estimaciones()->create(['num_estimacion' => '1', 'avance_fisico' => '40']);
    $obra->estimaciones()->create(['num_estimacion' => '2', 'avance_fisico' => '85']);
    // Sin avance: no debe ganarle a la anterior.
    $obra->estimaciones()->create(['num_estimacion' => '3']);

    $this->get(route('reportes.obra', ['columnas' => 'avfis']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filas.0.avfis', '85'));
});

it('el acuse de un papel se lee como Sí o No en el reporte', function () {
    $this->actingAs(usuarioReporteObra());

    $obra = obraConDatos();
    $obra->documentos()->create([
        'etapa' => 'adjudicacion',
        'clave' => 'fallo_contrato',
        'presente' => true,
    ]);

    $this->get(route('reportes.obra', ['columnas' => 'fallo,dictamen']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filas.0.fallo', 'Sí')
            // Sin capturar se queda vacío, no en "No".
            ->where('filas.0.dictamen', null));
});

it('filtra el reporte por modalidad de ejecución', function () {
    $this->actingAs(usuarioReporteObra());

    $conContrato = obraConDatos(['nombre' => 'POR CONTRATO']);
    $conContrato->autorizacion()->create(['mod_ejecucion' => '1']);
    $directa = obraConDatos(['nombre' => 'ADMINISTRACIÓN DIRECTA']);
    $directa->autorizacion()->create(['mod_ejecucion' => '2']);

    $this->get(route('reportes.obra', ['modalidad' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('filas', 1)
            ->where('filas.0.modalidad', 'CONTRATO'));
});

// El tablero suma `monto_inv` con `::numeric` y filtra con `~`, que son de
// Postgres; las pruebas corren en SQLite, así que sólo se ejecutan cuando la
// conexión es la real.
$soloPostgres = fn () => DB::connection()->getDriverName() !== 'pgsql'
    ? 'El tablero de obra usa SQL de PostgreSQL.'
    : false;

it('el tablero cuenta las siete etapas', function () {
    $this->actingAs(usuarioReporteObra());

    obraConDatos(['ficha_completo' => true, 'entrega_completo' => true]);
    obraConDatos(['ficha_completo' => true]);
    obraConDatos();

    $this->get(route('tableros.obra'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tableros/obra')
            ->where('resumen.total', 3)
            ->where('resumen.ficha', 2)
            ->where('resumen.entrega', 1)
            ->where('cumplimiento', 33.3));
})->skip($soloPostgres);

it('el mapa del tablero descarta coordenadas que no son números', function () {
    $this->actingAs(usuarioReporteObra());

    obraConDatos(['latitud' => '20.12', 'longitud' => '-98.73']);
    // El legacy dejó registros con "NaN": al castear valen 0 y el alfiler
    // se iría al Golfo de Guinea.
    obraConDatos(['latitud' => 'NaN', 'longitud' => 'NaN']);
    obraConDatos(['latitud' => '0', 'longitud' => '0']);

    $this->get(route('tableros.obra'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('obras', 1));
})->skip($soloPostgres);
