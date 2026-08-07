<?php

/**
 * QA adversarial — Categoría B: Obra pública.
 *
 * Escritos contra el comportamiento ESPERADO. Los que fallan documentan un
 * defecto pendiente de aprobación; no se toca código de producción.
 */

use App\Models\Beneficiario;
use App\Models\Obra;
use App\Models\ObraEstimacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function qaUsuarioObra(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function qaSolicitudObra(string $folio = 'DOP-100'): Solicitud
{
    $bene = Beneficiario::query()->create(['nombre' => 'ANA', 'paterno' => 'RUIZ']);

    return Solicitud::query()->create([
        'folio' => $folio,
        'folio_sistema' => $folio.'-1',
        'solicitud' => 'Drenaje en la colonia',
        'solicitante_type' => Beneficiario::class,
        'solicitante_id' => $bene->id,
        'status' => 0,
    ]);
}

function qaObra(?Solicitud $solicitud = null, string $nombre = 'OBRA DE PRUEBA'): Obra
{
    return Obra::query()->create([
        'solicitud_id' => $solicitud?->id,
        'num_obra' => 'OB-1',
        'nombre' => $nombre,
    ]);
}

// ---------------------------------------------------------------------------
// B-01 · HAPPY PATH (control: debe pasar)
// ---------------------------------------------------------------------------

it('B-01 marca la solicitud como importada al crear su expediente de obra', function () {
    $this->actingAs(qaUsuarioObra());
    $solicitud = qaSolicitudObra();

    $this->post(route('obra.store'), [
        'solicitud_id' => $solicitud->id,
        'nombre' => 'DRENAJE COLONIA CENTRO',
    ])->assertSessionHasNoErrors();

    expect($solicitud->fresh()->convertida_obra)->toBe(Solicitud::OBRA_IMPORTADA);
});

// ---------------------------------------------------------------------------
// B-02..B-03 · IDOR (controles positivos: el scoping actual debe aguantar)
// ---------------------------------------------------------------------------

it('B-02 no deja borrar una estimación que pertenece a otra obra', function () {
    $this->actingAs(qaUsuarioObra());
    $mia = qaObra(qaSolicitudObra('A'), 'MI OBRA');
    $ajena = qaObra(qaSolicitudObra('B'), 'OBRA AJENA');
    $estimacion = $ajena->estimaciones()->create(['num_estimacion' => '1']);

    $this->delete(route('obra.estimaciones.eliminar', ['obra' => $mia->id, 'estimacion' => $estimacion->id]))
        ->assertNotFound();

    expect(ObraEstimacion::query()->whereKey($estimacion->id)->exists())->toBeTrue();
});

it('B-03 no deja editar por id una estimación que pertenece a otra obra', function () {
    $this->actingAs(qaUsuarioObra());
    $mia = qaObra(qaSolicitudObra('A'), 'MI OBRA');
    $ajena = qaObra(qaSolicitudObra('B'), 'OBRA AJENA');
    $estimacion = $ajena->estimaciones()->create(['num_estimacion' => '1', 'monto' => '100']);

    $this->post(route('obra.estimaciones.guardar', $mia), [
        'id' => $estimacion->id,
        'monto' => '999999',
    ])->assertNotFound();

    expect($estimacion->fresh()->monto)->toBe('100');
});

// ---------------------------------------------------------------------------
// B-04..B-05 · INTEGRIDAD SOLICITUD ↔ OBRA
// ---------------------------------------------------------------------------

it('B-04 no permite dos expedientes de obra sobre la misma solicitud', function () {
    $this->actingAs(qaUsuarioObra());
    $solicitud = qaSolicitudObra();

    $this->post(route('obra.store'), ['solicitud_id' => $solicitud->id, 'nombre' => 'PRIMERA'])
        ->assertSessionHasNoErrors();

    $this->post(route('obra.store'), ['solicitud_id' => $solicitud->id, 'nombre' => 'SEGUNDA'])
        ->assertSessionHasErrors('solicitud_id');

    expect(Obra::query()->where('solicitud_id', $solicitud->id)->count())->toBe(1);
});

it('B-05 libera la solicitud anterior al reasignar el expediente a otra', function () {
    $this->actingAs(qaUsuarioObra());
    $vieja = qaSolicitudObra('VIEJA');
    $nueva = qaSolicitudObra('NUEVA');

    $this->post(route('obra.store'), ['solicitud_id' => $vieja->id, 'nombre' => 'OBRA']);
    $obra = Obra::query()->firstOrFail();

    // Se corrige la solicitud de origen del expediente.
    $this->post(route('obra.store'), [
        'id' => $obra->id,
        'solicitud_id' => $nueva->id,
        'nombre' => 'OBRA',
    ])->assertSessionHasNoErrors();

    expect($vieja->fresh()->convertida_obra)->toBe(Solicitud::OBRA_NO)
        ->and($nueva->fresh()->convertida_obra)->toBe(Solicitud::OBRA_IMPORTADA);
});

it('B-06 no desmarca la solicitud si todavía le queda otro expediente', function () {
    $this->actingAs(qaUsuarioObra());
    $solicitud = qaSolicitudObra();

    $primera = qaObra($solicitud, 'PRIMERA');
    qaObra($solicitud, 'SEGUNDA');
    $solicitud->update(['convertida_obra' => Solicitud::OBRA_IMPORTADA]);

    $this->delete(route('obra.destroy', $primera));

    // Sigue habiendo una obra viva para esa solicitud.
    expect($solicitud->fresh()->convertida_obra)->toBe(Solicitud::OBRA_IMPORTADA);
});

// ---------------------------------------------------------------------------
// B-07..B-09 · CAPTURA DE ETAPAS
// ---------------------------------------------------------------------------

it('B-07 rechaza una fecha inválida en los datos de una etapa', function () {
    $this->actingAs(qaUsuarioObra());
    $obra = qaObra(qaSolicitudObra());

    $this->put(route('obra.etapa.guardar', ['obra' => $obra->id, 'etapa' => 'autorizacion']), [
        'datos' => ['fecha_oficio_envio' => 'NO ES UNA FECHA'],
    ])->assertSessionHasErrors();

    expect($obra->fresh()->autorizacion)->toBeNull();
});

it('B-08 no deja marcar una etapa como completa si no se capturó nada', function () {
    $this->actingAs(qaUsuarioObra());
    $obra = qaObra(qaSolicitudObra());

    $this->put(route('obra.etapa.guardar', ['obra' => $obra->id, 'etapa' => 'autorizacion']), [
        'completo' => true,
    ])->assertSessionHasErrors();

    expect($obra->fresh()->autorizacion_completo)->toBeFalse();
});

it('B-09 no deja cerrar una etapa posterior si las anteriores siguen abiertas', function () {
    $this->actingAs(qaUsuarioObra());
    $obra = qaObra(qaSolicitudObra());

    // Se salta ficha, expediente, autorización, adjudicación, ejecución y
    // conclusión, y cierra directo la entrega-recepción.
    $this->put(route('obra.etapa.guardar', ['obra' => $obra->id, 'etapa' => 'entrega']), [
        'completo' => true,
        'datos' => ['fecha_acta_entrega_recepcion' => '2026-01-15'],
    ])->assertSessionHasErrors();

    expect($obra->fresh()->entrega_completo)->toBeFalse();
});

it('B-10 revierte los datos de la etapa si falla el guardado de documentos', function () {
    $this->actingAs(qaUsuarioObra());
    $obra = qaObra(qaSolicitudObra());

    $sabotaje = true;

    DB::listen(function ($query) use (&$sabotaje): void {
        if ($sabotaje && str_contains($query->sql, 'tbl_obra_documentos')) {
            throw new RuntimeException('fallo simulado al guardar los acuses');
        }
    });

    try {
        $this->put(route('obra.etapa.guardar', ['obra' => $obra->id, 'etapa' => 'autorizacion']), [
            'datos' => ['num_oficio_envio' => 'OF-123'],
            'documentos' => [['clave' => 'acta_comite', 'presente' => true]],
        ]);
    } catch (Throwable) {
        // El fallo es el escenario; se evalúa el estado de la BD.
    } finally {
        $sabotaje = false;
    }

    expect($obra->fresh()->autorizacion)->toBeNull();
});

// ---------------------------------------------------------------------------
// B-11 · PORTABILIDAD / COBERTURA DEL LISTADO DE ESTIMACIONES
// ---------------------------------------------------------------------------

it('B-11 lista las estimaciones ordenadas por número, en cualquier motor de BD', function () {
    $this->actingAs(qaUsuarioObra());
    $obra = qaObra(qaSolicitudObra());
    $obra->estimaciones()->create(['num_estimacion' => '10']);
    $obra->estimaciones()->create(['num_estimacion' => '2']);

    $r = $this->getJson(route('obra.estimaciones', $obra));

    $r->assertOk();
    expect($r->json('estimaciones.0.num_estimacion'))->toBe('2');
});

// ---------------------------------------------------------------------------
// B-12..B-13 · REGLAS DE NEGOCIO DE ESTIMACIONES
// ---------------------------------------------------------------------------

it('B-12 no permite una estimación que rebase el monto del contrato', function () {
    $this->actingAs(qaUsuarioObra());
    $obra = qaObra(qaSolicitudObra());
    $obra->adjudicacion()->create(['monto_contrato' => '100000']);

    $this->post(route('obra.estimaciones.guardar', $obra), [
        'num_estimacion' => '1',
        'monto' => '500000',
    ])->assertSessionHasErrors('monto');
});

it('B-13 no permite un avance físico mayor a 100%', function () {
    $this->actingAs(qaUsuarioObra());
    $obra = qaObra(qaSolicitudObra());

    $this->post(route('obra.estimaciones.guardar', $obra), [
        'num_estimacion' => '1',
        'avance_fisico' => '340',
        'avance_financiero' => '-20',
    ])->assertSessionHasErrors(['avance_fisico', 'avance_financiero']);
});

it('B-14 no permite estimaciones antes de que la obra esté adjudicada', function () {
    $this->actingAs(qaUsuarioObra());
    // Sin adjudicación: no hay contrato al que estimar.
    $obra = qaObra(qaSolicitudObra());

    $this->post(route('obra.estimaciones.guardar', $obra), ['num_estimacion' => '1'])
        ->assertSessionHasErrors();

    expect($obra->estimaciones()->count())->toBe(0);
});

it('B-15 no permite dos estimaciones con el mismo número en la misma obra', function () {
    $this->actingAs(qaUsuarioObra());
    $obra = qaObra(qaSolicitudObra());
    $obra->adjudicacion()->create(['monto_contrato' => '100000']);

    $this->post(route('obra.estimaciones.guardar', $obra), ['num_estimacion' => '1']);
    $this->post(route('obra.estimaciones.guardar', $obra), ['num_estimacion' => '1'])
        ->assertSessionHasErrors('num_estimacion');

    expect($obra->estimaciones()->count())->toBe(1);
});
