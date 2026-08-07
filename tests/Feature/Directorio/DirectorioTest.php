<?php

use App\Models\Beneficiario;
use App\Models\Organizacion;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioDirectorio(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

it('no lista nada hasta que se busca', function () {
    $this->actingAs(usuarioDirectorio());
    Beneficiario::query()->create(['nombre' => 'JUAN', 'paterno' => 'PEREZ', 'materno' => '']);

    $this->get(route('directorio.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('directorio/index')
            ->where('buscado', false)
            ->where('individuos', []));
});

it('busca individuos, organizaciones y proveedores a la vez', function () {
    $this->actingAs(usuarioDirectorio());

    Beneficiario::query()->create(['nombre' => 'ROSA', 'paterno' => 'MEZA', 'materno' => '']);
    Organizacion::query()->create(['nombre' => 'GRUPO MEZA']);
    Proveedor::query()->create(['nombre' => 'MEZA CONSTRUCCIONES']);

    $this->get(route('directorio.index', ['q' => 'MEZA']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('buscado', true)
            ->count('individuos', 1)
            ->count('organizaciones', 1)
            ->count('proveedores', 1)
            ->where('individuos.0.titulo', 'ROSA MEZA'));
});

it('respeta las casillas de Buscar en', function () {
    $this->actingAs(usuarioDirectorio());

    $bene = Beneficiario::query()->create([
        'nombre' => 'LUIS', 'paterno' => 'DIAZ', 'materno' => '', 'empresa' => 'PANADERIA LA ESPIGA',
    ]);
    $bene->comentarios()->create(['comentario' => 'Apoyó en la jornada de salud']);

    // Con sólo "Nombres" marcado, ni el trabajo ni el comentario cuentan.
    $soloNombres = ['campos' => ['nombres' => '1', 'comentarios' => '0', 'trabajo' => '0']];

    $this->get(route('directorio.index', ['q' => 'ESPIGA', ...$soloNombres]))
        ->assertInertia(fn ($page) => $page->where('individuos', []));

    $this->get(route('directorio.index', [
        'q' => 'ESPIGA',
        'campos' => ['nombres' => '0', 'comentarios' => '0', 'trabajo' => '1'],
    ]))->assertInertia(fn ($page) => $page->count('individuos', 1));

    $this->get(route('directorio.index', [
        'q' => 'jornada',
        'campos' => ['nombres' => '0', 'comentarios' => '1', 'trabajo' => '0'],
    ]))->assertInertia(fn ($page) => $page->count('individuos', 1));
});

it('filtra individuos por sexo sin filtrar por texto', function () {
    $this->actingAs(usuarioDirectorio());

    Beneficiario::query()->create(['nombre' => 'ANA', 'paterno' => 'RUIZ', 'materno' => '', 'genero' => 2]);
    Beneficiario::query()->create(['nombre' => 'MARIO', 'paterno' => 'SOTO', 'materno' => '', 'genero' => 1]);

    $this->get(route('directorio.index', ['genero' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('buscado', true)
            ->count('individuos', 1)
            ->where('individuos.0.titulo', 'ANA RUIZ'));
});

it('entrega el registro completo para editar', function () {
    $this->actingAs(usuarioDirectorio());

    $bene = Beneficiario::query()->create([
        'nombre' => 'ELSA', 'paterno' => 'NAVA', 'materno' => '', 'curp' => 'NAEL800101MDFVLS01',
    ]);

    $this->getJson(route('directorio.registro', ['tipo' => 'beneficiarios', 'id' => $bene->id]))
        ->assertOk()
        ->assertJsonPath('curp', 'NAEL800101MDFVLS01')
        ->assertJsonPath('nombre', 'ELSA');
});

it('abre el diálogo de edición cuando llega ?editar', function () {
    $this->actingAs(usuarioDirectorio());

    $bene = Beneficiario::query()->create(['nombre' => 'IRMA', 'paterno' => 'LARA', 'materno' => '']);

    $this->get(route('directorio.index', ['tab' => 'individuos', 'editar' => $bene->id, 'q' => 'IRMA LARA']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('editarId', $bene->id)
            ->where('tab', 'individuos')
            // Además busca su nombre, para que al cerrar el diálogo quede a la vista.
            ->count('individuos', 1));
});

it('manda las rutas viejas a su pestaña', function () {
    $this->actingAs(usuarioDirectorio());

    $this->get('/directorio/beneficiarios')
        ->assertRedirect(route('directorio.index', ['tab' => 'individuos']));
    $this->get('/directorio/proveedores')
        ->assertRedirect(route('directorio.index', ['tab' => 'proveedores']));
});
