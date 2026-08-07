<?php

use App\Http\Controllers\Reportes\ReporteController;
use App\Models\Beneficiario;
use App\Models\CatLocalidad;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function usuarioGestionVistas(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function solicitudPara(array $atributos = []): Solicitud
{
    $bene = Beneficiario::query()->create([
        'nombre' => $atributos['_nombre'] ?? 'Ana',
        'paterno' => 'Ruiz',
    ]);
    unset($atributos['_nombre']);

    return Solicitud::query()->create([
        'folio' => 'F-1',
        'folio_sistema' => 'F-1-1',
        'solicitud' => 'Bacheo en la calle',
        'solicitante_type' => Beneficiario::class,
        'solicitante_id' => $bene->id,
        'status' => 0,
        'fecha_recepcion' => '2019-03-04',
        ...$atributos,
    ]);
}

it('la vista por omisión es el detalle', function () {
    $this->actingAs(usuarioGestionVistas());
    solicitudPara();

    $this->get(route('reportes.gestion'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filtros.vista', 'detalle')
            ->where('opciones.eligeColumnas', true));
});

it('descarta una vista inventada', function () {
    $this->actingAs(usuarioGestionVistas());

    $this->get(route('reportes.gestion', ['vista' => 'inventada']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filtros.vista', 'detalle'));
});

it('el histórico deja fuera las solicitudes que se volvieron obra', function () {
    $this->actingAs(usuarioGestionVistas());

    // Cerradas: 2 no aprobada, 5 atendida, 6 atención rápida.
    solicitudPara(['status' => 5, '_nombre' => 'Cerrada sin obra']);
    solicitudPara(['status' => 5, 'convertida_obra' => Solicitud::OBRA_CONVERTIDA, '_nombre' => 'Convertida']);
    solicitudPara(['status' => 5, 'convertida_obra' => Solicitud::OBRA_IMPORTADA, '_nombre' => 'Importada']);
    // Abierta: no entra en ninguna de las dos vistas.
    solicitudPara(['status' => 3, '_nombre' => 'Abierta']);

    $this->get(route('reportes.gestion', ['vista' => 'historico']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('filas', 1));

    // Atendidas sí las incluye: sólo filtra por estatus.
    $this->get(route('reportes.gestion', ['vista' => 'atendidas']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('filas', 3));
});

it('histórico y atendidas traen sus columnas fijas', function () {
    $this->actingAs(usuarioGestionVistas());
    solicitudPara(['status' => 5]);

    $this->get(route('reportes.gestion', ['vista' => 'historico', 'columnas' => 'localidad']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('opciones.eligeColumnas', false)
            // Aunque se pida una sola, el legacy las manda todas.
            ->where('filtros.columnas', array_keys(
                ReporteController::COLUMNAS_GESTION_HISTORICO,
            )));
});

it('la vista general resume por localidad', function () {
    $this->actingAs(usuarioGestionVistas());

    $centro = CatLocalidad::query()->first();

    solicitudPara(['status' => 5, 'localidad_resp_id' => $centro?->id, 'monto' => '1000', 'num_bene' => '3']);
    solicitudPara(['status' => 3, 'localidad_resp_id' => $centro?->id, 'monto' => '500', 'num_bene' => '2']);
    solicitudPara(['status' => 0, 'localidad_resp_id' => null]);

    $this->get(route('reportes.gestion', ['vista' => 'general']))
        ->assertOk()
        ->assertInertia(function ($page) use ($centro) {
            $page->where('opciones.eligeColumnas', true)->has('filas', 2);

            $fila = collect($page->toArray()['props']['filas'])
                ->firstWhere('localidad', $centro?->nombre);

            expect($fila['total'])->toBe(2)
                ->and($fila['atendidas'])->toBe(1)
                ->and($fila['resolver'])->toBe(1)
                ->and((float) $fila['monto'])->toBe(1500.0);
        });
})->skip(
    fn () => DB::connection()->getDriverName() !== 'pgsql'
        ? 'La vista General agrega con SQL de PostgreSQL.'
        : false,
);

it('el Excel respeta la vista', function () {
    $this->actingAs(usuarioGestionVistas());
    solicitudPara(['status' => 5]);

    $this->get(route('reportes.gestion.excel', ['vista' => 'atendidas']))
        ->assertOk()
        ->assertDownload('reporte_gestion.xlsx');
});
