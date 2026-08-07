<?php

/**
 * QA adversarial — Categoría D: Seguridad ciudadana.
 *
 * Escritos contra el comportamiento ESPERADO. Los que fallan documentan un
 * defecto pendiente de aprobación; no se toca código de producción.
 */

use App\Models\CatTipoIncidencia;
use App\Models\Denuncia;
use App\Models\Detenido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function qaUsuarioSeguridad(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function qaDenuncia(array $extra = []): Denuncia
{
    return Denuncia::query()->create(array_merge([
        'denunciante_nombre' => 'MARIA',
        'denuncia' => 'Ruido excesivo en la vía pública',
        'fecha_denuncia' => '2026-01-15',
    ], $extra));
}

/**
 * @return array<string, mixed>
 */
function qaPayloadAtencion(): array
{
    return [
        'atendido_por' => 'OFICIAL RUIZ',
        'fecha_atencion' => '2026-01-16',
        'descripcion_situacion' => 'Se acudió al domicilio',
        'tipo_incidencia_id' => CatTipoIncidencia::query()->create(['nombre' => 'RUIDO'])->id,
    ];
}

// ---------------------------------------------------------------------------
// D-01 · HAPPY PATH (control: debe pasar)
// ---------------------------------------------------------------------------

it('D-01 recorre recepción → atención → término', function () {
    $this->actingAs(qaUsuarioSeguridad());
    $denuncia = qaDenuncia();

    $this->put(route('seguridad.denuncias.atender', $denuncia), qaPayloadAtencion())
        ->assertSessionHasNoErrors();
    expect($denuncia->fresh()->con_atencion)->toBeTrue();

    $this->put(route('seguridad.denuncias.concluir', $denuncia), [
        'conclusion' => 'Se resolvió con acuerdo entre vecinos',
        'con_termino' => true,
    ])->assertSessionHasNoErrors();
    expect($denuncia->fresh()->con_termino)->toBeTrue();
});

it('D-02 bloquea el término si no hubo toma de conocimiento (control)', function () {
    $this->actingAs(qaUsuarioSeguridad());
    $denuncia = qaDenuncia();

    $this->put(route('seguridad.denuncias.concluir', $denuncia), [
        'conclusion' => 'Cerrada sin atender',
        'con_termino' => true,
    ])->assertSessionHasErrors('con_termino');
});

// ---------------------------------------------------------------------------
// D-03..D-04 · BYPASS DE LA MÁQUINA DE ESTADOS POR EL UPSERT
// ---------------------------------------------------------------------------

it('D-03 no deja concluir una denuncia por el alta/edición saltándose el flujo', function () {
    $this->actingAs(qaUsuarioSeguridad());
    $denuncia = qaDenuncia();

    // `concluir()` valida que exista toma de conocimiento; `store()` acepta
    // `con_termino` como un campo cualquiera y se salta esa regla.
    $this->post(route('seguridad.denuncias.store'), [
        'id' => $denuncia->id,
        'con_termino' => true,
    ])->assertSessionHasErrors();

    expect($denuncia->fresh()->con_termino)->toBeFalse();
});

it('D-04 no deja marcar la toma de conocimiento sin capturarla', function () {
    $this->actingAs(qaUsuarioSeguridad());
    $denuncia = qaDenuncia();

    // `atender()` exige quién atiende, fecha, descripción y presunto delito;
    // `store()` deja poner la bandera sin nada de eso.
    $this->post(route('seguridad.denuncias.store'), [
        'id' => $denuncia->id,
        'con_atencion' => true,
    ])->assertSessionHasErrors();

    expect($denuncia->fresh()->con_atencion)->toBeFalse();
});

// ---------------------------------------------------------------------------
// D-05..D-07 · TRANSICIONES INVÁLIDAS
// ---------------------------------------------------------------------------

it('D-05 no deja rehacer la toma de conocimiento de una denuncia concluida', function () {
    $this->actingAs(qaUsuarioSeguridad());
    $denuncia = qaDenuncia(['con_atencion' => true, 'con_termino' => true, 'atendido_por' => 'OFICIAL ORIGINAL']);

    $this->put(route('seguridad.denuncias.atender', $denuncia), qaPayloadAtencion())
        ->assertSessionHasErrors();

    expect($denuncia->fresh()->atendido_por)->toBe('OFICIAL ORIGINAL');
});

it('D-06 no deja reabrir una denuncia ya concluida', function () {
    $this->actingAs(qaUsuarioSeguridad());
    $denuncia = qaDenuncia(['con_atencion' => true, 'con_termino' => true]);

    $this->put(route('seguridad.denuncias.concluir', $denuncia), ['con_termino' => false])
        ->assertSessionHasErrors();

    expect($denuncia->fresh()->con_termino)->toBeTrue();
});

it('D-07 no deja concluir sin acuerdos ni conclusión', function () {
    $this->actingAs(qaUsuarioSeguridad());
    $denuncia = qaDenuncia(['con_atencion' => true]);

    $this->put(route('seguridad.denuncias.concluir', $denuncia), ['con_termino' => true])
        ->assertSessionHasErrors();

    expect($denuncia->fresh()->con_termino)->toBeFalse();
});

// ---------------------------------------------------------------------------
// D-08..D-10 · FECHAS
// ---------------------------------------------------------------------------

it('D-08 rechaza una fecha de atención en el futuro', function () {
    $this->actingAs(qaUsuarioSeguridad());
    $denuncia = qaDenuncia();

    $this->put(route('seguridad.denuncias.atender', $denuncia), [
        ...qaPayloadAtencion(),
        'fecha_atencion' => now()->addYears(2)->toDateString(),
    ])->assertSessionHasErrors('fecha_atencion');
});

it('D-09 rechaza una fecha de denuncia que no es una fecha', function () {
    $this->actingAs(qaUsuarioSeguridad());

    $this->post(route('seguridad.denuncias.store'), [
        'denuncia' => 'Reporte',
        'fecha_denuncia' => 'AYER EN LA TARDE',
    ])->assertSessionHasErrors('fecha_denuncia');
});

it('D-10 filtra el rango de fechas por fecha real, no por orden alfabético', function () {
    $this->actingAs(qaUsuarioSeguridad());

    // `fecha_denuncia` es una columna de texto libre: si el capturista usa
    // dd/mm/aaaa el filtro compara cadenas y el rango deja de tener sentido.
    qaDenuncia(['denuncia' => 'ENERO', 'fecha_denuncia' => '15/01/2026']);
    qaDenuncia(['denuncia' => 'MARZO', 'fecha_denuncia' => '01/03/2026']);

    $r = $this->get(route('seguridad.denuncias.index', ['ini' => '01/02/2026', 'fin' => '31/12/2026']));

    $r->assertOk()->assertInertia(fn ($page) => $page
        ->has('denuncias', 1)
        ->where('denuncias.0.denuncia', 'MARZO'));
});

// ---------------------------------------------------------------------------
// D-11 · FUGA EN EL BUSCADOR
// ---------------------------------------------------------------------------

it('D-11 no devuelve denuncias cuando la búsqueda viene vacía', function () {
    $this->actingAs(qaUsuarioSeguridad());
    foreach (range(1, 5) as $i) {
        qaDenuncia(['denuncia' => "REPORTE {$i}"]);
    }

    $r = $this->getJson(route('seguridad.denuncias.buscar'));

    $r->assertOk();
    expect($r->json())->toHaveCount(0);
});

// ---------------------------------------------------------------------------
// D-12..D-14 · PDF (dependencia externa: FPDF, sólo maneja ISO-8859-1)
// ---------------------------------------------------------------------------

it('D-12 genera la ficha del detenido con caracteres fuera de latin-1', function () {
    $this->actingAs(qaUsuarioSeguridad());

    $detenido = Detenido::query()->create([
        'nombre' => 'JOSÉ 😀', 'paterno' => 'MUÑOZ', 'materno' => 'ÑÁÉÍÓÚ',
        'motivo_retencion' => 'Alteración del orden — riña 🚔',
    ]);

    $this->get(route('seguridad.detenidos.pdf', $detenido))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('D-13 genera la ficha del detenido con bytes UTF-8 inválidos sin perder el nombre', function () {
    $this->actingAs(qaUsuarioSeguridad());

    // Byte suelto 0x80: UTF-8 inválido. `iconv()` devuelve false y el campo
    // se convierte en cadena vacía sin avisar.
    $detenido = Detenido::query()->create([
        'nombre' => "PEDRO\x80", 'paterno' => 'SILVA',
    ]);

    $r = $this->get(route('seguridad.detenidos.pdf', $detenido));

    $r->assertOk();
    expect($r->streamedContent() ?: $r->getContent())->toContain('SILVA');
});

it('D-14 genera el PDF de la denuncia con un texto muy largo sin palabras', function () {
    $this->actingAs(qaUsuarioSeguridad());

    $denuncia = qaDenuncia([
        'denuncia' => str_repeat('A', 20000),
        'con_atencion' => true,
        'descripcion_situacion' => str_repeat('B', 20000),
    ]);

    $this->get(route('seguridad.denuncias.pdf', $denuncia))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// D-15 · CAPTURA DE FOTO DEL DETENIDO
// ---------------------------------------------------------------------------

it('D-15 permite capturar la foto del detenido que el listado dice mostrar', function () {
    $this->actingAs(qaUsuarioSeguridad());

    // El listado publica `tiene_foto` y la tarjeta pide `foto.img`, pero el
    // Request de alta/edición no acepta el campo `foto`.
    $this->post(route('seguridad.detenidos.store'), [
        'nombre' => 'PEDRO',
        'foto' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    ])->assertSessionHasNoErrors();

    expect(Detenido::query()->value('foto'))->not->toBeNull();
});
