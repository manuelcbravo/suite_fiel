<?php

use App\Models\Beneficiario;
use App\Models\Obra;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\DocumentosObra;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioEtapas(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function obraDePrueba(): Obra
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
    ]);
}

it('entrega los papeles de la etapa con lo ya capturado', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();
    $obra->documentos()->create([
        'etapa' => 'expediente',
        'clave' => 'validacion_campo',
        'presente' => true,
        'observacion' => 'Se validó en sitio',
    ]);

    $this->getJson(route('obra.etapa', ['obra' => $obra, 'etapa' => 'expediente']))
        ->assertOk()
        ->assertJsonPath('titulo', 'Expediente')
        ->assertJsonPath('completo', false)
        // Van todos los del catálogo, con o sin dato.
        ->assertJsonCount(count(DocumentosObra::de('expediente')), 'documentos')
        ->assertJsonPath('documentos.0.clave', 'validacion_campo')
        ->assertJsonPath('documentos.0.presente', true)
        ->assertJsonPath('documentos.0.observacion', 'Se validó en sitio');
});

it('rechaza una etapa que no existe', function () {
    $this->actingAs(usuarioEtapas());

    $this->getJson(route('obra.etapa', ['obra' => obraDePrueba(), 'etapa' => 'inventada']))
        ->assertNotFound();
});

it('guarda los acuses y cierra la etapa', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();

    $this->put(route('obra.etapa.guardar', ['obra' => $obra, 'etapa' => 'expediente']), [
        'completo' => true,
        'documentos' => [
            ['clave' => 'validacion_campo', 'presente' => true, 'observacion' => ''],
            ['clave' => 'carta_acep', 'presente' => false, 'observacion' => 'Falta firma'],
        ],
    ])->assertRedirect();

    $obra->refresh();
    expect($obra->expediente_completo)->toBeTrue()
        ->and($obra->documentos()->count())->toBe(2)
        ->and($obra->documentos()->where('clave', 'carta_acep')->value('observacion'))
        ->toBe('Falta firma');
});

it('borra el renglón cuando el papel se deja sin acuse ni observación', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();
    $obra->documentos()->create([
        'etapa' => 'expediente',
        'clave' => 'validacion_campo',
        'presente' => true,
    ]);

    $this->put(route('obra.etapa.guardar', ['obra' => $obra, 'etapa' => 'expediente']), [
        'completo' => false,
        'documentos' => [
            ['clave' => 'validacion_campo', 'presente' => null, 'observacion' => ''],
        ],
    ])->assertRedirect();

    expect($obra->documentos()->count())->toBe(0);
});

it('ignora claves que no son de la etapa', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();

    $this->put(route('obra.etapa.guardar', ['obra' => $obra, 'etapa' => 'expediente']), [
        'completo' => false,
        'documentos' => [
            // `fallo_contrato` es de adjudicación, no de expediente.
            ['clave' => 'fallo_contrato', 'presente' => true, 'observacion' => ''],
            ['clave' => 'inventada', 'presente' => true, 'observacion' => ''],
        ],
    ])->assertRedirect();

    expect($obra->documentos()->count())->toBe(0);
});

it('entrega los campos propios de la etapa agrupados', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();
    $obra->adjudicacion()->create([
        'num_contrato' => 'MPS-001',
        'fecha_contrato' => '2019-03-04',
        'monto_contrato' => '518,268.79',
    ]);

    $this->getJson(route('obra.etapa', ['obra' => $obra, 'etapa' => 'adjudicacion']))
        ->assertOk()
        ->assertJsonPath('datos.num_contrato', 'MPS-001')
        // La fecha viaja lista para el input date.
        ->assertJsonPath('datos.fecha_contrato', '2019-03-04')
        ->assertJsonPath('datos.monto_contrato', '518,268.79')
        ->assertJsonPath('grupos.0.titulo', 'Licitación');
});

it('crea el registro de la etapa en la primera captura', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();
    expect($obra->ejecucion)->toBeNull();

    $this->put(route('obra.etapa.guardar', ['obra' => $obra, 'etapa' => 'ejecucion']), [
        'completo' => false,
        'datos' => ['residente' => 'ING. LÓPEZ', 'fecha_inicio_real' => '2019-05-01'],
    ])->assertRedirect();

    $obra->refresh();
    expect($obra->ejecucion->residente)->toBe('ING. LÓPEZ')
        ->and($obra->ejecucion->fecha_inicio_real->toDateString())->toBe('2019-05-01');
});

it('guarda la lista de invitados al acto tal como la maneja el legacy', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();

    $this->put(route('obra.etapa.guardar', ['obra' => $obra, 'etapa' => 'entrega']), [
        'completo' => false,
        'datos' => ['invitados_acto' => '1,3,5'],
    ])->assertRedirect();

    expect($obra->refresh()->cierre->invitados_acto)->toBe('1,3,5');
});

it('conclusión y entrega escriben en la misma tabla sin pisarse', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();

    $this->put(route('obra.etapa.guardar', ['obra' => $obra, 'etapa' => 'conclusion']), [
        'completo' => false,
        'datos' => ['finiquito' => 'FIN-1'],
    ])->assertRedirect();

    $this->put(route('obra.etapa.guardar', ['obra' => $obra, 'etapa' => 'entrega']), [
        'completo' => false,
        'datos' => ['acta_entrega_recepcion' => 'ACTA-9'],
    ])->assertRedirect();

    $cierre = $obra->refresh()->cierre;
    expect($cierre->finiquito)->toBe('FIN-1')
        ->and($cierre->acta_entrega_recepcion)->toBe('ACTA-9');
});

it('ignora campos que no son de la etapa', function () {
    $this->actingAs(usuarioEtapas());

    $obra = obraDePrueba();

    $this->put(route('obra.etapa.guardar', ['obra' => $obra, 'etapa' => 'ejecucion']), [
        'completo' => false,
        // `num_contrato` es de adjudicación.
        'datos' => ['residente' => 'ING. LÓPEZ', 'num_contrato' => 'X-1'],
    ])->assertRedirect();

    expect($obra->refresh()->ejecucion->getAttributes())->not->toHaveKey('num_contrato');
});

it('el catálogo cubre las siete etapas y no repite claves', function () {
    $todos = DocumentosObra::todos();

    expect(array_keys($todos))->toBe(array_keys(Obra::ETAPAS));

    $claves = collect($todos)->flatMap(fn (array $docs) => array_keys($docs))->all();
    expect($claves)->toHaveCount(count(array_unique($claves)));
});
