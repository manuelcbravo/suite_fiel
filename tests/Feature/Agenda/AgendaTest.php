<?php

use App\Models\CatEstado;
use App\Models\CatLocalidad;
use App\Models\CatMunicipio;
use App\Models\CatTipoEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioAgenda(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function localidadDePrueba(): CatLocalidad
{
    $estado = CatEstado::query()->create(['nombre' => 'HIDALGO', 'clave' => '13']);
    $municipio = CatMunicipio::query()->create([
        'nombre' => 'PACHUCA DE SOTO', 'clave' => '048', 'estado_id' => $estado->id,
    ]);

    return CatLocalidad::query()->create([
        'nombre' => 'PACHUCA DE SOTO', 'clave' => '0001',
        'estado_id' => $estado->id, 'municipio_id' => $municipio->id,
    ]);
}

it('manda al calendario los tipos y las opciones de quién asiste', function () {
    $this->actingAs(usuarioAgenda());
    CatTipoEvento::query()->create(['nombre' => 'REUNIÓN', 'color' => '#2563eb']);

    $this->get(route('agenda.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('agenda/index')
            ->count('tipos', 1)
            // 1 = Presidente (a) · 2 = Representante
            ->count('opcionesAsiste', 2)
            ->where('opcionesAsiste.1.nombre', 'Representante'));
});

it('guarda el evento con localidad y quién asiste', function () {
    $this->actingAs(usuarioAgenda());
    $localidad = localidadDePrueba();

    $this->post(route('agenda.eventos.store'), [
        'titulo' => 'ENTREGA DE APOYOS',
        'inicio' => '2026-09-01 10:00:00',
        'fin' => '2026-09-01 12:00:00',
        'lugar' => 'PLAZA JUÁREZ',
        'localidad_id' => $localidad->id,
        'asiste' => 2,
        'representante' => 'LIC. GÓMEZ',
        'recomendaciones' => 'Vestimenta formal',
        'personas' => '150',
    ])->assertSessionHasNoErrors();

    $evento = Evento::query()->firstOrFail();

    expect($evento->asiste)->toBe(2)
        ->and($evento->localidad_id)->toBe($localidad->id)
        ->and($evento->recomendaciones)->toBe('Vestimenta formal');
});

it('rechaza un valor de quién asiste fuera del catálogo', function () {
    $this->actingAs(usuarioAgenda());

    $this->post(route('agenda.eventos.store'), [
        'titulo' => 'ALGO',
        'inicio' => '2026-09-01 10:00:00',
        'asiste' => 7,
    ])->assertSessionHasErrors('asiste');
});

it('el feed incluye lugar, privado y la cascada de la localidad', function () {
    $this->actingAs(usuarioAgenda());
    $localidad = localidadDePrueba();

    Evento::query()->create([
        'titulo' => 'SESIÓN DE CABILDO',
        'inicio' => '2026-09-10 09:00:00',
        'fin' => '2026-09-10 11:00:00',
        'lugar' => 'SALA DE CABILDO',
        'localidad_id' => $localidad->id,
        'privado' => true,
    ]);

    $respuesta = $this->getJson(route('agenda.eventos', [
        'start' => '2026-09-01T00:00:00Z',
        'end' => '2026-09-30T00:00:00Z',
    ]));

    $respuesta->assertOk()
        ->assertJsonPath('0.extendedProps.lugar', 'SALA DE CABILDO')
        ->assertJsonPath('0.extendedProps.privado', true)
        ->assertJsonPath('0.extendedProps.localidad_id', $localidad->id)
        // Estado y municipio viajan para poder precargar el selector en cascada.
        ->assertJsonPath('0.extendedProps.municipio_id', $localidad->municipio_id);
});
