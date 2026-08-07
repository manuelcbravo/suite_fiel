<?php

use App\Models\Beneficiario;
use App\Models\CatEstado;
use App\Models\CatFiliacion;
use App\Models\CatLocalidad;
use App\Models\CatMunicipio;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['es_super_admin' => true]));

    $this->estado = CatEstado::create(['nombre' => 'HIDALGO']);
    $this->municipio = CatMunicipio::create([
        'clave' => 48, 'nombre' => 'PACHUCA', 'estado_id' => $this->estado->id,
    ]);
    // La sección vive en la localidad: es de donde el formulario la copia.
    $this->localidad = CatLocalidad::create([
        'clave' => 900, 'nombre' => 'PACHUCA CENTRO', 'estado_id' => $this->estado->id,
        'municipio_id' => $this->municipio->id, 'seccion' => 892,
        'distrito_federal' => 4, 'tipo_seccion' => 'URBANO(A)',
    ]);
});

it('entrega la sección junto con la localidad para que el alta la copie', function () {
    $respuesta = $this->getJson(
        "/directorio/ubicaciones/localidades?municipio_id={$this->municipio->id}"
    );

    $respuesta->assertOk()->assertJsonFragment([
        'id' => $this->localidad->id,
        'nombre' => 'PACHUCA CENTRO',
        'seccion' => 892,
    ]);
});

it('guarda la sección y el perfil político del beneficiario', function () {
    $filiacion = CatFiliacion::create(['nombre' => 'MORENA']);

    $this->post('/directorio/beneficiarios', [
        'nombre' => 'JUANA',
        'paterno' => 'PEREZ',
        'estado_id' => $this->estado->id,
        'municipio_id' => $this->municipio->id,
        'localidad_id' => $this->localidad->id,
        'seccion' => 892,
        'filiacion_id' => $filiacion->id,
    ])->assertRedirect();

    $b = Beneficiario::where('nombre', 'JUANA')->sole();

    expect($b->seccion)->toBe(892)
        ->and($b->filiacion->nombre)->toBe('MORENA');
});

it('rechaza una filiación que no está en el catálogo', function () {
    $this->post('/directorio/beneficiarios', [
        'nombre' => 'JUANA',
        'paterno' => 'PEREZ',
        'filiacion_id' => 9999,
    ])->assertSessionHasErrors('filiacion_id');
});

it('manda los cuatro catálogos nuevos a la pantalla de directorio', function () {
    CatFiliacion::create(['nombre' => 'MORENA']);

    $this->get('/directorio')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->has('opciones.filiaciones')
            ->has('opciones.antagonicos')
            ->has('opciones.lideres')
            ->has('opciones.influencias')
            ->where('opciones.filiaciones.0.nombre', 'MORENA')
        );
});
