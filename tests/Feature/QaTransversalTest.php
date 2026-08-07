<?php

/**
 * QA adversarial — Categoría G: capa transversal (rutas, permisos, catálogos,
 * auditoría y límites de tasa).
 *
 * Escritos contra el comportamiento ESPERADO. Los que fallan documentan un
 * defecto pendiente de aprobación; no se toca código de producción.
 */

use App\Enums\Permiso;
use App\Models\Beneficiario;
use App\Models\CatConcepto;
use App\Models\Denuncia;
use App\Models\Detenido;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function qaSuperAdmin(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

function qaConPermiso(string ...$permisos): User
{
    $user = User::factory()->create(['es_super_admin' => false]);

    foreach ($permisos as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }

    return $user;
}

// ---------------------------------------------------------------------------
// G-01..G-02 · AISLAMIENTO ENTRE MÓDULOS (controles: deben pasar)
// ---------------------------------------------------------------------------

it('G-01 no deja entrar a un módulo sin su permiso', function () {
    $this->actingAs(qaConPermiso('gestion.gestionar'));

    $this->get(route('seguridad.denuncias.index'))->assertForbidden();
    $this->get(route('obra.index'))->assertForbidden();
    $this->get(route('directorio.index'))->assertForbidden();
    $this->get(route('config.users.index'))->assertForbidden();
});

it('G-02 responde 404 a un catálogo que no existe (control)', function () {
    $this->actingAs(qaSuperAdmin());

    $this->post(route('config.catalogos.store', ['catalogo' => 'usuarios']), ['nombre' => 'X'])
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// G-03..G-04 · BORRADO DE CATÁLOGOS EN USO
// ---------------------------------------------------------------------------

it('G-03 avisa antes de borrar un catálogo que está en uso', function () {
    $this->actingAs(qaSuperAdmin());

    $concepto = CatConcepto::query()->create(['nombre' => 'VIVIENDA']);
    $bene = Beneficiario::query()->create(['nombre' => 'ANA', 'paterno' => 'RUIZ']);
    Solicitud::query()->create([
        'folio' => 'F-1', 'solicitante_type' => Beneficiario::class, 'solicitante_id' => $bene->id,
        'concepto_id' => $concepto->id, 'status' => 0,
    ]);

    $this->delete(route('config.catalogos.destroy', ['catalogo' => 'conceptos', 'id' => $concepto->id]))
        ->assertSessionHasErrors();

    expect(CatConcepto::query()->whereKey($concepto->id)->exists())->toBeTrue();
});

it('G-04 no deja solicitudes apuntando a un catálogo que ya no existe', function () {
    $this->actingAs(qaSuperAdmin());

    $concepto = CatConcepto::query()->create(['nombre' => 'VIVIENDA']);
    $bene = Beneficiario::query()->create(['nombre' => 'ANA', 'paterno' => 'RUIZ']);
    $solicitud = Solicitud::query()->create([
        'folio' => 'F-1', 'solicitante_type' => Beneficiario::class, 'solicitante_id' => $bene->id,
        'concepto_id' => $concepto->id, 'status' => 0,
    ]);

    $this->delete(route('config.catalogos.destroy', ['catalogo' => 'conceptos', 'id' => $concepto->id]));

    // El invariante es que `concepto_id` siempre resuelva. Con SoftDeletes en
    // el catálogo la FK nunca se dispara, así que el borrado se bloquea
    // mientras haya expedientes usándolo.
    expect($solicitud->fresh()->concepto)->not->toBeNull();
});

it('G-04b sí deja borrar un catálogo que no usa nadie', function () {
    $this->actingAs(qaSuperAdmin());

    $concepto = CatConcepto::query()->create(['nombre' => 'SIN USO']);

    $this->delete(route('config.catalogos.destroy', ['catalogo' => 'conceptos', 'id' => $concepto->id]))
        ->assertSessionHasNoErrors();

    expect(CatConcepto::query()->whereKey($concepto->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// G-05 · AUDITORÍA
// ---------------------------------------------------------------------------

it('G-05 audita los modelos sensibles', function () {
    // `owen-it/laravel-auditing` está en composer.json pero ningún modelo lo
    // implementa: no queda rastro de quién borró una denuncia o un detenido.
    expect(new Denuncia)->toBeInstanceOf(Auditable::class)
        ->and(new Detenido)->toBeInstanceOf(Auditable::class)
        ->and(new Solicitud)->toBeInstanceOf(Auditable::class);
});

// ---------------------------------------------------------------------------
// G-06 · PERMISOS DECLARADOS QUE NO PROTEGEN NADA
// ---------------------------------------------------------------------------

it('G-06 todo permiso declarado se usa en alguna ruta', function () {
    $usados = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($r) => $r->gatherMiddleware())
        ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'can:'))
        ->map(fn ($m) => substr($m, 4))
        ->unique();

    $declarados = collect(Permiso::cases())->map(fn (Permiso $p) => $p->value);
    $huerfanos = $declarados->diff($usados)->values()->all();

    expect($huerfanos)->toBe([]);
});

it('G-07 cada permiso de reporte abre su reporte', function () {
    $this->actingAs(qaConPermiso(Permiso::ReporteAgenda->value));

    $this->get(route('reportes.agenda'))->assertOk();
    // Y sólo el suyo: no da acceso a los demás submódulos.
    $this->get(route('reportes.gestion'))->assertForbidden();
});

// ---------------------------------------------------------------------------
// G-08 · LÍMITE DE TASA EN OPERACIONES CARAS
// ---------------------------------------------------------------------------

it('G-08 limita la tasa de descargas de Excel', function () {
    $this->actingAs(qaSuperAdmin());

    $ultima = null;
    foreach (range(1, 25) as $i) {
        $ultima = $this->get(route('reportes.agenda.excel'));
    }

    // El login está limitado a 5/min, pero un export que barre todo el padrón
    // se puede pedir sin tope.
    expect($ultima?->baseResponse->getStatusCode())->toBe(429);
});

// ---------------------------------------------------------------------------
// G-09 · SEPARACIÓN LECTURA / ESCRITURA
// ---------------------------------------------------------------------------

it('G-09 distingue consultar de borrar dentro de un módulo', function () {
    $denuncia = Denuncia::query()->create(['denuncia' => 'Reporte', 'fecha_denuncia' => '2026-01-10']);

    // `seguridad.gestionar` es un permiso único: quien puede consultar el
    // módulo puede además borrar expedientes de forma definitiva.
    $consulta = qaConPermiso('seguridad.gestionar');
    $this->actingAs($consulta);

    $this->delete(route('seguridad.denuncias.destroy', $denuncia))->assertForbidden();
});
