<?php

use App\Models\Evento;
use App\Models\Nota;
use App\Models\Notita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioNotas(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function nota(string $texto, string $fecha, ?Evento $evento = null): Nota
{
    return Nota::query()->create([
        'nota' => $texto,
        'fecha' => $fecha,
        'evento_id' => $evento?->id,
    ]);
}

it('abre en la ventana de ayer y hoy, como el legacy', function () {
    $this->actingAs(usuarioNotas());

    nota('De hoy', now()->toDateString());
    nota('De ayer', now()->subDay()->toDateString());
    nota('De la semana pasada', now()->subWeek()->toDateString());

    $this->get(route('notas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notas/index')
            ->where('filtros.desde', now()->subDay()->toDateString())
            ->where('filtros.hasta', now()->toDateString())
            ->has('notas', 2));
});

it('respeta el rango de fechas que se pide', function () {
    $this->actingAs(usuarioNotas());

    nota('Vieja', '2019-03-04');
    nota('De hoy', now()->toDateString());

    $this->get(route('notas.index', ['desde' => '2019-01-01', 'hasta' => '2019-12-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notas', 1)
            ->where('notas.0.nota', 'Vieja'));
});

it('no lista la nota cuyo evento fue borrado', function () {
    $this->actingAs(usuarioNotas());

    $vivo = Evento::query()->create(['titulo' => 'Sesión de cabildo', 'inicio' => '2019-03-04 09:00:00']);
    $muerto = Evento::query()->create(['titulo' => 'Cumpleaños', 'inicio' => '2019-03-05 09:00:00']);

    nota('Colgada de un evento vivo', '2019-03-04', $vivo);
    nota('Colgada de un evento borrado', '2019-03-04', $muerto);
    nota('Sin evento', '2019-03-04');

    $muerto->delete();

    $this->get(route('notas.index', ['desde' => '2019-01-01', 'hasta' => '2019-12-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notas', 2)
            ->where('notas.0.nota', 'Colgada de un evento vivo')
            ->where('notas.1.nota', 'Sin evento'));
});

it('manda los datos del evento vinculado', function () {
    $this->actingAs(usuarioNotas());

    $evento = Evento::query()->create([
        'titulo' => 'Entrega de apoyos',
        'inicio' => '2019-03-04 11:30:00',
        'lugar' => 'Plaza Juárez',
    ]);
    nota('Llevar el guion', '2019-03-04', $evento);

    $this->get(route('notas.index', ['desde' => '2019-01-01', 'hasta' => '2019-12-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('notas.0.evento', [
            'titulo' => 'Entrega de apoyos',
            'fecha' => '2019-03-04',
            'hora' => '11:30',
            'lugar' => 'Plaza Juárez',
        ]));
});

it('edita el texto de un pendiente', function () {
    $this->actingAs(usuarioNotas());

    $n = nota('Acuerdos', now()->toDateString());
    $p = $n->pendientes()->create(['texto' => 'Hablar con tesorería', 'realizado' => false]);

    $this->put(route('notas.pendientes.update', $p), ['texto' => 'Hablar con tesorería el lunes'])
        ->assertRedirect();

    expect($p->refresh()->texto)->toBe('Hablar con tesorería el lunes');
});

it('no deja vaciar el texto de un pendiente', function () {
    $this->actingAs(usuarioNotas());

    $n = nota('Acuerdos', now()->toDateString());
    $p = $n->pendientes()->create(['texto' => 'Hablar con tesorería', 'realizado' => false]);

    $this->put(route('notas.pendientes.update', $p), ['texto' => ''])
        ->assertSessionHasErrors('texto');

    expect($p->refresh()->texto)->toBe('Hablar con tesorería');
});

it('marca y desmarca un pendiente', function () {
    $this->actingAs(usuarioNotas());

    $n = nota('Acuerdos', now()->toDateString());
    /** @var Notita $p */
    $p = $n->pendientes()->create(['texto' => 'Firmar oficio', 'realizado' => false]);

    $this->put(route('notas.pendientes.toggle', $p));
    expect($p->refresh()->realizado)->toBeTrue();

    $this->put(route('notas.pendientes.toggle', $p));
    expect($p->refresh()->realizado)->toBeFalse();
});
