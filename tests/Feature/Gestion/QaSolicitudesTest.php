<?php

/**
 * QA adversarial — Categoría A: Gestión / Solicitudes.
 *
 * Estos tests están escritos contra el comportamiento ESPERADO, no contra el
 * comportamiento actual. Los que fallan documentan un defecto; no se corrige
 * código de producción hasta que el hallazgo sea aprobado.
 */

use App\Models\Beneficiario;
use App\Models\CatArea;
use App\Models\CatConcepto;
use App\Models\CatDependencia;
use App\Models\CatEstado;
use App\Models\CatLocalidad;
use App\Models\CatMunicipio;
use App\Models\CatRubro;
use App\Models\CatSector;
use App\Models\CatUnidadMedida;
use App\Models\Seguimiento;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function qaUsuario(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function qaBeneficiario(string $nombre = 'JUAN'): Beneficiario
{
    return Beneficiario::query()->create([
        'nombre' => $nombre, 'paterno' => 'PEREZ', 'materno' => 'LOPEZ',
    ]);
}

function qaSolicitud(Beneficiario $bene, int $status = 0, ?string $folioSistema = '100'): Solicitud
{
    return Solicitud::query()->create([
        'folio' => 'F-'.$folioSistema,
        'folio_sistema' => $folioSistema,
        'solicitud' => 'Techo de lámina',
        'solicitante_type' => Beneficiario::class,
        'solicitante_id' => $bene->id,
        'status' => $status,
        'bene_final' => 1,
    ]);
}

/**
 * Catálogos mínimos para que `capturaCompleta()` pueda dar true.
 *
 * @return array<string, int>
 */
function qaCatalogos(): array
{
    $estado = CatEstado::query()->create(['nombre' => 'HIDALGO']);
    $municipio = CatMunicipio::query()->create(['clave' => 48, 'nombre' => 'PACHUCA', 'estado_id' => $estado->id]);
    $localidad = CatLocalidad::query()->create([
        'clave' => 1, 'nombre' => 'PACHUCA CENTRO',
        'municipio_id' => $municipio->id, 'estado_id' => $estado->id,
    ]);

    return [
        'estado_id' => $estado->id,
        'municipio_id' => $municipio->id,
        'localidad_id' => $localidad->id,
        'concepto_id' => CatConcepto::query()->create(['nombre' => 'VIVIENDA'])->id,
        'unidad_id' => CatUnidadMedida::query()->create(['nombre' => 'PIEZA'])->id,
        'rubro_id' => CatRubro::query()->create(['nombre' => 'SOCIAL'])->id,
        'sector_id' => CatSector::query()->create(['nombre' => 'ADULTOS MAYORES'])->id,
    ];
}

/**
 * Payload de `atender` con decisión "resolver" y captura COMPLETA.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function qaPayloadResolver(array $cat, array $override = []): array
{
    return array_merge([
        'decision' => 'resolver',
        'respuesta' => 'Se entregó el apoyo',
        'apoyo' => 'LÁMINAS',
        'cantidad' => '10',
        'unidad_medida_id' => $cat['unidad_id'],
        'monto' => '1000',
        'num_bene' => '4',
        'concepto_id' => $cat['concepto_id'],
        'estado_resp_id' => $cat['estado_id'],
        'municipio_resp_id' => $cat['municipio_id'],
        'localidad_resp_id' => $cat['localidad_id'],
        'folio_resp' => 'R-001',
        'fecha_resp' => '2026-08-01',
        'rubros' => [$cat['rubro_id']],
        'sectores' => [$cat['sector_id']],
    ], $override);
}

// ---------------------------------------------------------------------------
// A-01 · HAPPY PATH (control: debe pasar)
// ---------------------------------------------------------------------------

it('A-01 cierra la solicitud cuando la captura de resolución está completa', function () {
    $this->actingAs(qaUsuario());
    $cat = qaCatalogos();
    $solicitud = qaSolicitud(qaBeneficiario(), status: 3);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    $this->post(route('gestion.solicitudes.atender', $solicitud), qaPayloadResolver($cat))
        ->assertSessionHasNoErrors();

    expect($solicitud->fresh()->status)->toBe(5)
        ->and($solicitud->fresh()->rubros)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// A-02..A-03 · LÍMITES / VALIDACIÓN DE TIPOS
// ---------------------------------------------------------------------------

it('A-02 rechaza un monto no numérico al resolver', function () {
    $this->actingAs(qaUsuario());
    $cat = qaCatalogos();
    $solicitud = qaSolicitud(qaBeneficiario(), status: 3);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    $this->post(
        route('gestion.solicitudes.atender', $solicitud),
        qaPayloadResolver($cat, ['monto' => 'DIEZ MIL PESOS; DROP TABLE'])
    )->assertSessionHasErrors('monto');
});

it('A-03 rechaza un monto negativo y una cantidad negativa', function () {
    $this->actingAs(qaUsuario());
    $cat = qaCatalogos();
    $solicitud = qaSolicitud(qaBeneficiario(), status: 3);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    $this->post(
        route('gestion.solicitudes.atender', $solicitud),
        qaPayloadResolver($cat, ['monto' => '-5000', 'cantidad' => '-10'])
    )->assertSessionHasErrors(['monto', 'cantidad']);
});

it('A-04 rechaza una fecha de respuesta inválida en vez de cerrar la solicitud', function () {
    $this->actingAs(qaUsuario());
    $cat = qaCatalogos();
    $solicitud = qaSolicitud(qaBeneficiario(), status: 3);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    $this->post(
        route('gestion.solicitudes.atender', $solicitud),
        qaPayloadResolver($cat, ['fecha_resp' => 'NO ES UNA FECHA'])
    )->assertSessionHasErrors('fecha_resp');

    expect($solicitud->fresh()->status)->not->toBe(5);
});

// ---------------------------------------------------------------------------
// A-05..A-08 · MÁQUINA DE ESTADOS
// ---------------------------------------------------------------------------

it('A-05 no permite atender una solicitud que nunca fue turnada', function () {
    $this->actingAs(qaUsuario());
    // status 0 = Capturada, sin ningún seguimiento.
    $solicitud = qaSolicitud(qaBeneficiario(), status: 0);

    $this->post(route('gestion.solicitudes.atender', $solicitud), [
        'decision' => 'rapida',
        'respuesta' => 'Se entregó en el momento',
    ])->assertSessionHasErrors();

    expect($solicitud->fresh()->status)->toBe(0);
});

it('A-06 no permite degradar una solicitud ya Atendida a Respuesta de área', function () {
    $this->actingAs(qaUsuario());
    $solicitud = qaSolicitud(qaBeneficiario(), status: 5);
    $solicitud->seguimientos()->create(['estatus' => 3, 'estatus_resp' => 5]);

    $this->post(route('gestion.solicitudes.atender', $solicitud), [
        'decision' => 'temporal',
        'respuesta' => 'Reabro el trámite',
    ])->assertSessionHasErrors();

    expect($solicitud->fresh()->status)->toBe(5);
});

it('A-07 no permite que responder() degrade una solicitud ya Atendida', function () {
    $this->actingAs(qaUsuario());
    $solicitud = qaSolicitud(qaBeneficiario(), status: 5);
    $seguimiento = $solicitud->seguimientos()->create(['estatus' => 3, 'estatus_resp' => 5]);

    $this->put(route('gestion.seguimientos.responder', $seguimiento), [
        'respuesta' => 'Respuesta tardía del área',
    ])->assertSessionHasErrors();

    expect($solicitud->fresh()->status)->toBe(5);
});

it('A-08 no permite reasignar un área a un seguimiento sin dependencia (No aprobada)', function () {
    $this->actingAs(qaUsuario());
    $solicitud = qaSolicitud(qaBeneficiario(), status: 2);
    // "No aprobada" no se turna: dependencia_id y area_id quedan en null.
    $seguimiento = $solicitud->seguimientos()->create(['estatus' => 2]);

    $dependencia = CatDependencia::query()->create(['nombre' => 'OBRAS PÚBLICAS']);
    $area = CatArea::query()->create(['nombre' => 'PAVIMENTOS', 'dependencia_id' => $dependencia->id]);

    $this->put(route('gestion.seguimientos.reasignar', $seguimiento), ['area_id' => $area->id])
        ->assertSessionHasErrors('area_id');

    expect($seguimiento->fresh()->area_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// A-09 · ROLLBACK DE TRANSACCIONES
// ---------------------------------------------------------------------------

it('A-09 revierte el cierre de la solicitud si falla el guardado de rubros', function () {
    $this->actingAs(qaUsuario());
    $cat = qaCatalogos();
    $solicitud = qaSolicitud(qaBeneficiario(), status: 3);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    // Simula un fallo de la BD justo al escribir el pivote de rubros, después
    // de que la solicitud ya se guardó con status 5. El interruptor deja de
    // sabotear en cuanto termina la petición, para poder consultar la base.
    $sabotaje = true;

    DB::listen(function ($query) use (&$sabotaje): void {
        if ($sabotaje && str_contains($query->sql, 'tbl_solicitud_rubro')) {
            throw new RuntimeException('fallo simulado en el pivote de rubros');
        }
    });

    try {
        $this->post(route('gestion.solicitudes.atender', $solicitud), qaPayloadResolver($cat));
    } catch (Throwable) {
        // El fallo es el escenario; lo que se evalúa es el estado de la BD.
    } finally {
        $sabotaje = false;
    }

    // Sin transacción, la solicitud queda "Atendida" pero sin rubros/sectores
    // y con el seguimiento sin actualizar: estado inconsistente.
    expect($solicitud->fresh()->status)->toBe(3)
        ->and(DB::table('tbl_solicitud_rubro')->where('solicitud_id', $solicitud->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// A-10..A-11 · INTEGRIDAD DE DATOS
// ---------------------------------------------------------------------------

it('A-10 no permite crear una solicitud sin solicitante', function () {
    $this->actingAs(qaUsuario());

    $this->post(route('gestion.solicitudes.store'), [
        'folio' => 'F-SIN-DUEÑO',
        'solicitud' => 'Solicitud huérfana',
    ])->assertSessionHasErrors();

    expect(Solicitud::query()->where('folio', 'F-SIN-DUEÑO')->exists())->toBeFalse();
});

it('A-11 no permite un solicitante incoherente con el tipo declarado', function () {
    $this->actingAs(qaUsuario());
    $bene = qaBeneficiario('ANA');

    // Tipo "organizacion" pero sólo se manda un beneficiario_id.
    $this->post(route('gestion.solicitudes.store'), [
        'solicitante_tipo' => 'organizacion',
        'beneficiario_id' => $bene->id,
        'folio' => 'F-INCOHERENTE',
        'solicitud' => 'Tipo que no cuadra',
    ])->assertSessionHasErrors();

    expect(Solicitud::query()->where('folio', 'F-INCOHERENTE')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// A-12 · ORDEN DEL EXPEDIENTE
// ---------------------------------------------------------------------------

it('A-12 ordena el expediente por folio de sistema numérico, no alfabético', function () {
    $this->actingAs(qaUsuario());
    $bene = qaBeneficiario('PEDRO');

    qaSolicitud($bene, folioSistema: '9');
    qaSolicitud($bene, folioSistema: '10');

    // El folio de sistema es un consecutivo: el 10 es posterior al 9 y debe ir
    // primero en el orden descendente.
    $this->get(route('gestion.solicitudes.index', ['q' => 'PEDRO']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('personas.0.solicitudes.0.folio_sistema', '10'));
});

// ---------------------------------------------------------------------------
// A-13 · CONCURRENCIA / IDEMPOTENCIA
// ---------------------------------------------------------------------------

it('A-13 no duplica el cierre cuando llegan dos resoluciones seguidas', function () {
    $this->actingAs(qaUsuario());
    $cat = qaCatalogos();
    $solicitud = qaSolicitud(qaBeneficiario(), status: 3);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    $payload = qaPayloadResolver($cat);

    $this->post(route('gestion.solicitudes.atender', $solicitud), $payload);
    // Segundo envío (doble clic / reintento del navegador): debe ser rechazado
    // porque la solicitud ya está cerrada.
    $this->post(route('gestion.solicitudes.atender', $solicitud), $payload)
        ->assertSessionHasErrors();
});

it('A-14 no permite registrar una verificación con fecha futura', function () {
    $this->actingAs(qaUsuario());
    $solicitud = qaSolicitud(qaBeneficiario(), status: 5);

    $this->post(route('gestion.verificaciones.store', $solicitud), [
        'comentario' => 'Verificación adelantada',
        'fecha' => now()->addYear()->toDateString(),
    ])->assertSessionHasErrors('fecha');
});

// ---------------------------------------------------------------------------
// A-15 · SOFT DELETE
// ---------------------------------------------------------------------------

it('A-15 no permite editar por id una solicitud borrada', function () {
    $this->actingAs(qaUsuario());
    $solicitud = qaSolicitud(qaBeneficiario(), status: 0);
    $solicitud->delete();

    // `exists:tbl_solicitudes,id` no filtra deleted_at, así que la validación
    // pasa y el findOrFail revienta con un 404 en vez de dar un error de campo.
    $this->post(route('gestion.solicitudes.store'), [
        'id' => $solicitud->id,
        'folio' => 'F-RESUCITADA',
    ])->assertSessionHasErrors('id');
});

// ---------------------------------------------------------------------------
// A-16 · CONSISTENCIA SEMÁNTICA DEL CAMPO `avance`
// ---------------------------------------------------------------------------

it('A-16 usa la misma escala de avance en atender() y en responder()', function () {
    $this->actingAs(qaUsuario());
    $cat = qaCatalogos();
    $solicitud = qaSolicitud(qaBeneficiario(), status: 3);
    $solicitud->seguimientos()->create(['estatus' => 1]);

    $this->post(route('gestion.solicitudes.atender', $solicitud), qaPayloadResolver($cat));

    /** @var Seguimiento $seguimiento */
    $seguimiento = $solicitud->seguimientos()->latest('id')->first();

    // `responder()` valida avance como porcentaje 0-100. Una solicitud resuelta
    // por completo debería quedar en 100, no en 1.
    expect($seguimiento->avance)->toBe(100);
});
