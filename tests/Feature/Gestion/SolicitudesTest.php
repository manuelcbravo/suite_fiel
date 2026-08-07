<?php

use App\Models\Beneficiario;
use App\Models\CatDependencia;
use App\Models\Seguimiento;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Usuario con acceso al módulo (bypass de super admin). */
function usuarioGestion(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function solicitudDe(Beneficiario $bene, int $status = 0): Solicitud
{
    return Solicitud::query()->create([
        'folio' => 'F-100',
        'folio_sistema' => '100',
        'solicitud' => 'Techo de lámina para la vivienda',
        'solicitante_type' => Beneficiario::class,
        'solicitante_id' => $bene->id,
        'status' => $status,
        'bene_final' => 1,
    ]);
}

it('no lista nada hasta que se busca', function () {
    $this->actingAs(usuarioGestion());

    $this->get(route('gestion.solicitudes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('gestion/solicitudes/index')
            ->where('buscado', false)
            ->where('personas', [])
            ->where('organizaciones', []));
});

it('agrupa las solicitudes bajo su solicitante al buscar', function () {
    $this->actingAs(usuarioGestion());

    $bene = Beneficiario::query()->create(['nombre' => 'JUAN', 'paterno' => 'PEREZ', 'materno' => 'LOPEZ']);
    solicitudDe($bene);

    // El nombre se busca por palabras, en cualquier orden.
    $this->get(route('gestion.solicitudes.index', ['q' => 'PEREZ JUAN']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('buscado', true)
            ->where('personas.0.nombre', 'JUAN PEREZ LOPEZ')
            ->where('personas.0.solicitudes.0.folio_sistema', '100')
            ->where('personas.0.solicitudes.0.status_label', 'CAPTURADA')
            // bene_final = 1 → el beneficiario es el propio solicitante.
            ->where('personas.0.solicitudes.0.beneficiario_nombre', 'JUAN PEREZ LOPEZ'));
});

it('sirve las solicitudes de un expediente de 30 en 30', function () {
    $this->actingAs(usuarioGestion());

    $bene = Beneficiario::query()->create(['nombre' => 'PEDRO', 'paterno' => 'VELA', 'materno' => '']);
    foreach (range(1, 35) as $i) {
        solicitudDe($bene);
    }

    $this->get(route('gestion.solicitudes.index', ['q' => 'PEDRO']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('personas.0.solicitudes_total', 35)
            ->count('personas.0.solicitudes', 30));

    // El "ver más" trae el resto sin repetir las ya mostradas.
    $resto = $this->getJson(route('gestion.solicitantes.solicitudes', [
        'tipo' => 'beneficiario', 'id' => $bene->id, 'desde' => 30,
    ]));

    $resto->assertOk();
    expect($resto->json())->toHaveCount(5);
});

it('abre el expediente de un solicitante concreto desde el Directorio', function () {
    $this->actingAs(usuarioGestion());

    $bene = Beneficiario::query()->create(['nombre' => 'CARMEN', 'paterno' => 'LARA', 'materno' => '']);
    $otro = Beneficiario::query()->create(['nombre' => 'OTRO', 'paterno' => 'AJENO', 'materno' => '']);
    solicitudDe($bene);
    solicitudDe($otro);

    // Sin texto de búsqueda: entra directo al expediente y trae sólo ese.
    $this->get(route('gestion.solicitudes.index', [
        'solicitante_tipo' => 'beneficiario',
        'solicitante_id' => $bene->id,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('buscado', true)
            ->count('personas', 1)
            ->where('personas.0.nombre', 'CARMEN LARA')
            ->where('organizaciones', []));
});

it('filtra por estatus', function () {
    $this->actingAs(usuarioGestion());

    $bene = Beneficiario::query()->create(['nombre' => 'ANA', 'paterno' => 'RUIZ', 'materno' => '']);
    solicitudDe($bene, status: 0);

    $this->get(route('gestion.solicitudes.index', ['estatus' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('personas', []));

    $this->get(route('gestion.solicitudes.index', ['estatus' => 0]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->count('personas', 1));
});

it('turna la solicitud y la mueve al estatus elegido', function () {
    $this->actingAs(usuarioGestion());

    $bene = Beneficiario::query()->create(['nombre' => 'LUIS', 'paterno' => 'DIAZ', 'materno' => '']);
    $solicitud = solicitudDe($bene);
    $dependencia = CatDependencia::query()->create(['nombre' => 'OBRAS PÚBLICAS']);

    $this->post(route('gestion.seguimientos.store', $solicitud), [
        'estatus' => 1,
        'dependencia_id' => $dependencia->id,
        'responsable' => 'ING. GOMEZ',
        'instruccion' => 'Atender a la brevedad',
    ])->assertRedirect();

    expect($solicitud->fresh()->status)->toBe(1)
        ->and(Seguimiento::query()->where('solicitud_id', $solicitud->id)->value('responsable'))
        ->toBe('ING. GOMEZ');
});

it('exige dependencia y responsable al turnar, pero no al no aprobar', function () {
    $this->actingAs(usuarioGestion());

    $bene = Beneficiario::query()->create(['nombre' => 'ROSA', 'paterno' => 'MEZA', 'materno' => '']);
    $solicitud = solicitudDe($bene);

    $this->post(route('gestion.seguimientos.store', $solicitud), ['estatus' => 1])
        ->assertSessionHasErrors(['dependencia_id', 'responsable', 'instruccion']);

    $this->post(route('gestion.seguimientos.store', $solicitud), [
        'estatus' => 2,
        'comentario' => 'No procede por falta de recurso',
    ])->assertSessionHasNoErrors();

    expect($solicitud->fresh()->status)->toBe(2);
});

it('registra la atención rápida sin exigir el detalle del apoyo', function () {
    $this->actingAs(usuarioGestion());

    $bene = Beneficiario::query()->create(['nombre' => 'MARIO', 'paterno' => 'SOTO', 'materno' => '']);
    $solicitud = solicitudDe($bene, status: 1);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    $this->post(route('gestion.solicitudes.atender', $solicitud), [
        'decision' => 'rapida',
        'respuesta' => 'Se entregó el apoyo en el momento',
    ])->assertSessionHasNoErrors();

    expect($solicitud->fresh()->status)->toBe(6);
});

it('no cierra la solicitud al resolver si falta capturar el apoyo', function () {
    $this->actingAs(usuarioGestion());

    $bene = Beneficiario::query()->create(['nombre' => 'ELsA', 'paterno' => 'NAVA', 'materno' => '']);
    $solicitud = solicitudDe($bene, status: 3);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    $this->post(route('gestion.solicitudes.atender', $solicitud), [
        'decision' => 'resolver',
        'respuesta' => 'Pendiente de entrega',
        'apoyo' => 'LÁMINAS',
    ])->assertSessionHasNoErrors();

    // Sigue "Para resolver" porque la captura quedó incompleta.
    expect($solicitud->fresh()->status)->toBe(3);
});
