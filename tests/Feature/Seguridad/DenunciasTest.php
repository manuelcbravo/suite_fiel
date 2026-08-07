<?php

use App\Models\CatTipoIncidencia;
use App\Models\Denuncia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioSeguridad(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function denunciaDePrueba(array $extra = []): Denuncia
{
    return Denuncia::query()->create([
        'denunciante_nombre' => 'JUAN',
        'denunciante_paterno' => 'PEREZ',
        'denuncia' => 'Ruido excesivo en la vía pública',
        'fecha_denuncia' => '2026-03-10',
        ...$extra,
    ]);
}

it('filtra por etapa del flujo', function () {
    $this->actingAs(usuarioSeguridad());

    denunciaDePrueba();                                        // recepción
    denunciaDePrueba(['con_atencion' => true]);                // atención
    denunciaDePrueba(['con_atencion' => true, 'con_termino' => true]); // término

    $this->get(route('seguridad.denuncias.index', ['estatus' => 1]))
        ->assertInertia(fn ($page) => $page->count('denuncias', 1));
    $this->get(route('seguridad.denuncias.index', ['estatus' => 2]))
        ->assertInertia(fn ($page) => $page->count('denuncias', 1));
    $this->get(route('seguridad.denuncias.index', ['estatus' => 3]))
        ->assertInertia(fn ($page) => $page->count('denuncias', 1));
});

it('filtra por rango de fechas', function () {
    $this->actingAs(usuarioSeguridad());

    denunciaDePrueba(['fecha_denuncia' => '2026-01-05']);
    denunciaDePrueba(['fecha_denuncia' => '2026-06-20']);

    $this->get(route('seguridad.denuncias.index', ['ini' => '2026-06-01', 'fin' => '2026-06-30']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->count('denuncias', 1));
});

it('la toma de conocimiento deja la denuncia en atención', function () {
    $this->actingAs(usuarioSeguridad());

    $denuncia = denunciaDePrueba();
    $tipo = CatTipoIncidencia::query()->create(['nombre' => 'RIÑA']);

    $this->put(route('seguridad.denuncias.atender', $denuncia), [
        'atendido_por' => 'OFICIAL RAMÍREZ',
        'fecha_atencion' => '2026-03-11',
        'hora_atencion' => '14:30',
        'descripcion_situacion' => 'Se atendió el reporte en el lugar',
        'tipo_incidencia_id' => $tipo->id,
    ])->assertSessionHasNoErrors();

    $denuncia->refresh();

    expect($denuncia->con_atencion)->toBeTrue()
        ->and($denuncia->atendido_por)->toBe('OFICIAL RAMÍREZ')
        ->and($denuncia->estatusLabel())->toBe('En atención');
});

it('exige quién atiende y el presunto delito', function () {
    $this->actingAs(usuarioSeguridad());

    $this->put(route('seguridad.denuncias.atender', denunciaDePrueba()), [])
        ->assertSessionHasErrors(['atendido_por', 'descripcion_situacion', 'tipo_incidencia_id']);
});

it('no deja concluir una denuncia sin toma de conocimiento', function () {
    $this->actingAs(usuarioSeguridad());
    $denuncia = denunciaDePrueba();

    $this->put(route('seguridad.denuncias.concluir', $denuncia), [
        'conclusion' => 'Se resolvió en el lugar',
        'con_termino' => true,
    ])->assertSessionHasErrors('con_termino');

    expect($denuncia->fresh()->con_termino)->toBeFalse();
});

it('concluye la denuncia cuando ya fue atendida', function () {
    $this->actingAs(usuarioSeguridad());
    $denuncia = denunciaDePrueba(['con_atencion' => true]);

    $this->put(route('seguridad.denuncias.concluir', $denuncia), [
        'acuerdos_convenios' => 'Las partes llegaron a un acuerdo',
        'conclusion' => 'Sin más que informar',
        'con_termino' => true,
    ])->assertSessionHasNoErrors();

    expect($denuncia->fresh()->estatusLabel())->toBe('Concluida');
});
