<?php

use App\Http\Controllers\Reportes\ReporteController;
use App\Models\Beneficiario;
use App\Models\Evento;
use App\Models\Proveedor;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioReportes(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

it('usa las columnas por omisión cuando no se pide ninguna', function () {
    $this->actingAs(usuarioReportes());

    Evento::query()->create([
        'titulo' => 'Reunión de gabinete',
        'inicio' => '2026-03-04 09:00:00',
        'fin' => '2026-03-04 10:30:00',
        'lugar' => 'Sala de juntas',
        'privado' => true,
    ]);

    $this->get(route('reportes.agenda'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reportes/agenda')
            ->where('filtros.columnas', ['titulo', 'tipo', 'fecha', 'hora', 'lugar', 'contacto'])
            // La fila trae exactamente las columnas aplicadas, ni una más.
            ->where('filas.0', [
                'titulo' => 'Reunión de gabinete',
                'tipo' => null,
                'fecha' => '2026-03-04',
                'hora' => '09:00 - 10:30',
                'lugar' => 'Sala de juntas',
                'contacto' => null,
            ]));
});

it('respeta las columnas pedidas y las devuelve en el orden del catálogo', function () {
    $this->actingAs(usuarioReportes());

    Evento::query()->create([
        'titulo' => 'Entrega de apoyos',
        'inicio' => '2026-03-05 11:00:00',
        'privado' => false,
        'asiste' => 1,
    ]);

    // Se piden en desorden a propósito: deben salir como manda el catálogo.
    $this->get(route('reportes.agenda', ['columnas' => 'clasificacion,titulo,asiste']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filtros.columnas', ['titulo', 'asiste', 'clasificacion'])
            ->where('filas.0', [
                'titulo' => 'Entrega de apoyos',
                'asiste' => 'PRESIDENTE MUNICIPAL',
                'clasificacion' => 'PÚBLICO',
            ]));
});

it('ignora columnas que no existen en el catálogo', function () {
    $this->actingAs(usuarioReportes());

    Evento::query()->create(['titulo' => 'Sesión de cabildo', 'inicio' => '2026-03-06 08:00:00']);

    $this->get(route('reportes.agenda', ['columnas' => 'titulo,inventada,DROP TABLE']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filtros.columnas', ['titulo']));
});

it('cada directorio tiene su propio catálogo de columnas', function () {
    $this->actingAs(usuarioReportes());

    Proveedor::query()->create([
        'nombre' => 'Constructora del Valle',
        'rfc' => 'CVA010203ABC',
        'especialidad' => 'URBANIZACIÓN',
        'num_prov_gob' => '2740',
        'calle' => 'Allende',
        'num_ext' => '15',
        'colonia' => 'Centro',
        'cp' => '42000',
    ]);

    $this->get(route('reportes.directorio', [
        'tipo' => 'proveedores',
        'columnas' => 'nombre,rfc,padron,direccion',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filtros.columnas', ['nombre', 'rfc', 'padron', 'direccion'])
            ->where('filas.0', [
                'nombre' => 'Constructora del Valle',
                'rfc' => 'CVA010203ABC',
                'padron' => '2740',
                'direccion' => 'Allende #15, Centro, C.P. 42000',
            ])
            // El catálogo que viaja al front es el de la pestaña activa.
            ->where('opciones.columnas', fn ($cols) => collect($cols)->pluck('clave')->all()
                === array_keys(ReporteController::COLUMNAS_DIRECTORIO['proveedores'])));
});

it('una columna de otro directorio no se cuela', function () {
    $this->actingAs(usuarioReportes());

    Beneficiario::query()->create(['nombre' => 'Ana', 'paterno' => 'Ruiz', 'genero' => 2]);

    // `padron` sólo existe en proveedores: al pedirla desde ciudadanos se
    // descarta y quedan las de por omisión.
    $this->get(route('reportes.directorio', ['tipo' => 'ciudadanos', 'columnas' => 'padron']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filtros.columnas.0', 'nombre')
            ->where('filas.0.nombre', 'Ana Ruiz')
            ->where('filas.0.sexo', 'Femenino'));
});

it('el reporte de gestión arma las columnas compuestas del legacy', function () {
    $this->actingAs(usuarioReportes());

    $bene = Beneficiario::query()->create(['nombre' => 'Luis', 'paterno' => 'Mora']);
    Solicitud::query()->create([
        'folio' => 'PM-070-2020',
        'solicitud' => 'Pañales para adulto',
        'solicitante_type' => Beneficiario::class,
        'solicitante_id' => $bene->id,
        'status' => 5,
        'bene_final' => 3,
        'control_administrativo' => true,
    ]);

    $this->get(route('reportes.gestion', ['columnas' => 'foliosolicitud,tipoben,tp_sol']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filas.0', [
                'foliosolicitud' => 'PM-070-2020',
                'tipoben' => 'COMUNITARIO',
                'tp_sol' => 'DE CONTROL ADMINISTRATIVO',
            ]));
});

it('el Excel se descarga con las columnas pedidas', function () {
    $this->actingAs(usuarioReportes());

    Evento::query()->create(['titulo' => 'Informe de gobierno', 'inicio' => '2026-03-07 18:00:00']);

    $this->get(route('reportes.agenda.excel', ['columnas' => 'titulo,clasificacion']))
        ->assertOk()
        ->assertDownload('reporte_agenda.xlsx');
});
