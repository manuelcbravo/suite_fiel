<?php

/**
 * QA adversarial — Categoría F: Agenda, Notas e Invitaciones.
 *
 * Escritos contra el comportamiento ESPERADO. Los que fallan documentan un
 * defecto pendiente de aprobación; no se toca código de producción.
 */

use App\Models\Evento;
use App\Models\Invitacion;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function qaUsuarioAgenda(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

/** Usuario con un solo permiso de módulo. */
function qaUsuarioCon(string $permiso): User
{
    Permission::findOrCreate($permiso, 'web');
    $user = User::factory()->create(['es_super_admin' => false]);
    $user->givePermissionTo($permiso);

    return $user;
}

function qaEvento(array $extra = []): Evento
{
    return Evento::query()->create(array_merge([
        'titulo' => 'REUNIÓN VECINAL',
        'inicio' => '2026-03-10 09:00:00',
        'fin' => '2026-03-10 11:00:00',
    ], $extra));
}

// ---------------------------------------------------------------------------
// F-01..F-02 · HAPPY PATH Y LÍMITE (controles: deben pasar)
// ---------------------------------------------------------------------------

it('F-01 crea un evento', function () {
    $this->actingAs(qaUsuarioAgenda());

    $this->post(route('agenda.eventos.store'), [
        'titulo' => 'SESIÓN DE CABILDO',
        'inicio' => '2026-03-10 09:00:00',
        'fin' => '2026-03-10 11:00:00',
    ])->assertSessionHasNoErrors();

    expect(Evento::query()->count())->toBe(1);
});

it('F-02 rechaza un evento que termina antes de empezar (control)', function () {
    $this->actingAs(qaUsuarioAgenda());

    $this->post(route('agenda.eventos.store'), [
        'titulo' => 'IMPOSIBLE',
        'inicio' => '2026-03-10 11:00:00',
        'fin' => '2026-03-10 09:00:00',
    ])->assertSessionHasErrors('fin');
});

// ---------------------------------------------------------------------------
// F-03..F-05 · FEED DE FULLCALENDAR
// ---------------------------------------------------------------------------

it('F-03 no vuelca la agenda completa cuando no se manda rango', function () {
    $this->actingAs(qaUsuarioAgenda());
    foreach (range(1, 40) as $i) {
        qaEvento(['titulo' => "EVENTO {$i}"]);
    }

    // Sin `start`/`end` el feed devuelve todos los eventos de la historia.
    $r = $this->getJson(route('agenda.eventos'));

    $r->assertOk();
    expect(count($r->json()))->toBeLessThan(40);
});

it('F-04 no revienta con un rango de fechas basura', function () {
    $this->actingAs(qaUsuarioAgenda());
    qaEvento();

    $r = $this->getJson(route('agenda.eventos', ['start' => 'AYER', 'end' => 'MAÑANA']));

    expect($r->status())->not->toBe(500);
});

it('F-05 no expone el detalle de un evento privado en el feed', function () {
    $this->actingAs(qaUsuarioAgenda());
    qaEvento(['titulo' => 'REUNIÓN RESERVADA', 'privado' => true, 'descripcion' => 'Tema delicado']);

    $r = $this->getJson(route('agenda.eventos', [
        'start' => '2026-03-01', 'end' => '2026-03-31',
    ]));

    $r->assertOk();
    expect($r->json('0.extendedProps.descripcion'))->toBeNull();
});

// ---------------------------------------------------------------------------
// F-06..F-08 · NOTAS DEL EVENTO
// ---------------------------------------------------------------------------

it('F-06 no crea registros al sólo consultar las notas de un evento', function () {
    $this->actingAs(qaUsuarioAgenda());
    $evento = qaEvento();

    // Un GET no debería escribir en la base.
    $this->getJson(route('agenda.eventos.notas', $evento))->assertOk();

    expect(Nota::query()->count())->toBe(0);
});

it('F-07 impide dos notas para el mismo evento', function () {
    $evento = qaEvento();

    // `notaDelEvento()` usa firstOrNew + save sin unique en `evento_id`: dos
    // peticiones concurrentes crean dos notas del mismo evento.
    Nota::query()->create(['evento_id' => $evento->id, 'nota' => 'A', 'fecha' => '2026-03-10']);

    expect(fn () => Nota::query()->create([
        'evento_id' => $evento->id, 'nota' => 'B', 'fecha' => '2026-03-10',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('F-08 no deja tocar pendientes de notas sueltas desde el módulo de agenda', function () {
    // Nota libre, sin evento: no pertenece a la agenda.
    $nota = Nota::query()->create(['nota' => 'PENDIENTES PERSONALES', 'fecha' => '2026-03-10']);
    $pendiente = $nota->pendientes()->create(['texto' => 'Llamar al proveedor', 'realizado' => false]);

    $this->actingAs(qaUsuarioCon('agenda.gestionar'));

    $this->delete(route('agenda.eventos.notas.destroy', $pendiente))
        ->assertForbidden();

    expect($nota->pendientes()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// F-09 · HUÉRFANOS AL BORRAR EL EVENTO
// ---------------------------------------------------------------------------

it('F-09 no deja pendientes accesibles de un evento borrado', function () {
    $this->actingAs(qaUsuarioAgenda());
    $evento = qaEvento();

    $this->post(route('agenda.eventos.notas.store', $evento), ['texto' => 'Confirmar sonido']);
    $pendiente = Nota::query()->firstOrFail()->pendientes()->firstOrFail();

    $this->delete(route('agenda.eventos.destroy', $evento))->assertRedirect();

    // La nota queda huérfana (`evento_id` a null) y el listado la esconde,
    // pero sus pendientes siguen vivos y manipulables por id.
    $this->put(route('notas.pendientes.toggle', $pendiente))->assertNotFound();
});

// ---------------------------------------------------------------------------
// F-10..F-12 · INVITACIONES / CORREO (dependencia externa)
// ---------------------------------------------------------------------------

it('F-10 envía realmente el correo al notificar una invitación', function () {
    Mail::fake();
    $this->actingAs(qaUsuarioAgenda());

    $invitacion = Invitacion::query()->create([
        'titulo' => 'INAUGURACIÓN', 'inicio' => '2026-03-10 09:00:00',
    ]);

    $this->post(route('invitaciones.notificar', $invitacion), [
        'correos' => 'alguien@ejemplo.mx',
        'mensaje' => 'Le esperamos',
    ])->assertSessionHasNoErrors();

    // Se registra `enviado_en = now()` sin que salga un solo correo.
    Mail::assertSentCount(1);
});

it('F-11 no marca como enviado un correo que nunca salió', function () {
    Mail::fake();
    $this->actingAs(qaUsuarioAgenda());

    $invitacion = Invitacion::query()->create([
        'titulo' => 'INAUGURACIÓN', 'inicio' => '2026-03-10 09:00:00',
    ]);

    $this->post(route('invitaciones.notificar', $invitacion), ['correos' => 'alguien@ejemplo.mx']);

    expect($invitacion->correos()->value('enviado_en'))->toBeNull();
});

it('F-12 valida que los destinatarios sean correos', function () {
    $this->actingAs(qaUsuarioAgenda());

    $invitacion = Invitacion::query()->create([
        'titulo' => 'INAUGURACIÓN', 'inicio' => '2026-03-10 09:00:00',
    ]);

    $this->post(route('invitaciones.notificar', $invitacion), [
        'correos' => 'esto no es un correo; ni esto tampoco',
    ])->assertSessionHasErrors('correos');
});
