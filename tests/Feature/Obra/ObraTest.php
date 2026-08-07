<?php

use App\Models\Beneficiario;
use App\Models\CatEje;
use App\Models\CatLocalidad;
use App\Models\CatSubeje;
use App\Models\Obra;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioObra(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function solicitudDeObra(string $folio = 'DOP-001', string $texto = 'Drenaje en la colonia'): Solicitud
{
    $bene = Beneficiario::query()->create(['nombre' => 'Ana', 'paterno' => 'Ruiz']);

    return Solicitud::query()->create([
        'folio' => $folio,
        'folio_sistema' => $folio.'-99',
        'solicitud' => $texto,
        'solicitante_type' => Beneficiario::class,
        'solicitante_id' => $bene->id,
        'status' => 0,
    ]);
}

it('lista las obras con su folio de recepción tomado de la solicitud', function () {
    $this->actingAs(usuarioObra());

    $solicitud = solicitudDeObra('DOP-007', 'Pavimentación de la calle');
    Obra::query()->create([
        'solicitud_id' => $solicitud->id,
        'num_obra' => 'OB-1',
        'nombre' => 'PAVIMENTACIÓN CALLE HIDALGO',
    ]);

    $this->get(route('obra.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('obra/index')
            ->has('obras', 1)
            ->where('obras.0.nombre', 'PAVIMENTACIÓN CALLE HIDALGO')
            ->where('obras.0.folio', 'DOP-007-99'));
});

it('usa el nombre autorizado por encima del capturado', function () {
    $this->actingAs(usuarioObra());

    $obra = Obra::query()->create([
        'solicitud_id' => solicitudDeObra()->id,
        'nombre' => 'NOMBRE CAPTURADO',
    ]);
    $obra->autorizacion()->create(['nombre_obra_aut' => 'NOMBRE AUTORIZADO']);

    $this->get(route('obra.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('obras.0.nombre', 'NOMBRE AUTORIZADO'));
});

it('cae al texto de la solicitud cuando la obra no tiene nombre', function () {
    $this->actingAs(usuarioObra());

    Obra::query()->create([
        'solicitud_id' => solicitudDeObra('DOP-010', 'Alumbrado en la plaza')->id,
    ]);

    $this->get(route('obra.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('obras.0.nombre', 'Alumbrado en la plaza'));
});

it('filtra por etapa cerrada y por etapa pendiente', function () {
    $this->actingAs(usuarioObra());

    Obra::query()->create([
        'solicitud_id' => solicitudDeObra('A')->id,
        'nombre' => 'CON FICHA',
        'ficha_completo' => true,
    ]);
    Obra::query()->create([
        'solicitud_id' => solicitudDeObra('B')->id,
        'nombre' => 'SIN FICHA',
    ]);

    $this->get(route('obra.index', ['etapa' => 'ficha']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('obras', 1)->where('obras.0.nombre', 'CON FICHA'));

    $this->get(route('obra.index', ['pendiente' => 'ficha']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('obras', 1)->where('obras.0.nombre', 'SIN FICHA'));
});

it('ignora una etapa que no existe', function () {
    $this->actingAs(usuarioObra());

    Obra::query()->create(['solicitud_id' => solicitudDeObra()->id, 'nombre' => 'UNA OBRA']);

    $this->get(route('obra.index', ['etapa' => 'inventada; drop table']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filtros.etapa', null)->has('obras', 1));
});

it('la adjudicación queda bloqueada hasta que hay modalidad de ejecución', function () {
    $this->actingAs(usuarioObra());

    $sin = Obra::query()->create(['solicitud_id' => solicitudDeObra('A')->id, 'nombre' => 'SIN MODALIDAD']);
    $con = Obra::query()->create(['solicitud_id' => solicitudDeObra('B')->id, 'nombre' => 'CON MODALIDAD']);
    $sin->autorizacion()->create(['mod_ejecucion' => null]);
    $con->autorizacion()->create(['mod_ejecucion' => '1']);

    $this->get(route('obra.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('obras.0.con_modalidad', false)
            ->where('obras.1.con_modalidad', true));
});

it('la ficha manda estado y municipio para poder preseleccionar la localidad', function () {
    $this->actingAs(usuarioObra());

    // La obra sólo guarda la localidad; la cascada necesita los tres niveles.
    $localidad = CatLocalidad::query()->first()
        ?? CatLocalidad::query()->create(['clave' => 1, 'nombre' => 'CENTRO']);

    $obra = Obra::query()->create([
        'solicitud_id' => solicitudDeObra()->id,
        'localidad_id' => $localidad->id,
    ]);

    $this->getJson(route('obra.ficha', $obra))
        ->assertOk()
        ->assertJson([
            'localidad_id' => $localidad->id,
            'estado_id' => $localidad->estado_id,
            'municipio_id' => $localidad->municipio_id,
            'localidad_nombre' => $localidad->nombre,
        ]);
});

it('guarda la ficha técnica sin perder el subeje', function () {
    $this->actingAs(usuarioObra());

    $eje = CatEje::query()->create(['nombre' => 'PACHUCA ORDENADA']);
    $subeje = CatSubeje::query()->create(['nombre' => 'INFRAESTRUCTURA', 'eje_id' => $eje->id]);

    $obra = Obra::query()->create([
        'solicitud_id' => solicitudDeObra()->id,
        'nombre' => 'OBRA',
        'pmd_eje_id' => $eje->id,
        'pmd_subeje_id' => $subeje->id,
    ]);

    $this->post(route('obra.store'), [
        'id' => $obra->id,
        'nombre' => 'OBRA CORREGIDA',
        'pmd_eje_id' => $eje->id,
        'pmd_subeje_id' => $subeje->id,
        'monto_inv' => '1,200,000.00',
    ])->assertRedirect();

    $obra->refresh();
    expect($obra->nombre)->toBe('OBRA CORREGIDA')
        ->and($obra->pmd_subeje_id)->toBe($subeje->id)
        ->and($obra->monto_inv)->toBe('1,200,000.00');
});

it('crea la obra desde una solicitud', function () {
    $this->actingAs(usuarioObra());

    $solicitud = solicitudDeObra('DOP-500');

    $this->post(route('obra.store'), [
        'solicitud_id' => $solicitud->id,
        'nombre' => 'OBRA NUEVA',
    ])->assertRedirect();

    expect(Obra::query()->where('solicitud_id', $solicitud->id)->exists())->toBeTrue();
});

it('el buscador de solicitudes no ofrece las que ya son obra', function () {
    $this->actingAs(usuarioObra());

    $libre = solicitudDeObra('LIBRE-1', 'Bacheo en la avenida');
    $tomada = solicitudDeObra('TOMADA-1', 'Bacheo en la calle');
    Obra::query()->create(['solicitud_id' => $tomada->id]);

    $respuesta = $this->getJson(route('obra.solicitudes', ['q' => 'Bacheo']))->assertOk();

    $ids = collect($respuesta->json())->pluck('id')->all();
    expect($ids)->toContain($libre->id)->not->toContain($tomada->id);
});
