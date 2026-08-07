<?php

/**
 * QA adversarial — Categoría C: Directorio.
 *
 * Escritos contra el comportamiento ESPERADO. Los que fallan documentan un
 * defecto pendiente de aprobación; no se toca código de producción.
 */

use App\Models\Beneficiario;
use App\Models\Comentario;
use App\Models\Detenido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function qaUsuarioDirectorio(): User
{
    return User::factory()->create(['es_super_admin' => true]);
}

/** Usuario con SÓLO el permiso del directorio: no debe ver nada de seguridad. */
function qaSoloDirectorio(): User
{
    Permission::findOrCreate('directorio.gestionar', 'web');
    $user = User::factory()->create(['es_super_admin' => false]);
    $user->givePermissionTo('directorio.gestionar');

    return $user;
}

function qaBene(string $nombre = 'JUAN', array $extra = []): Beneficiario
{
    return Beneficiario::query()->create(array_merge([
        'nombre' => $nombre, 'paterno' => 'PEREZ', 'materno' => 'LOPEZ',
    ], $extra));
}

/** PNG de 1x1 real, como data URI. */
function qaPngDataUri(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

// ---------------------------------------------------------------------------
// C-01 · HAPPY PATH (control: debe pasar)
// ---------------------------------------------------------------------------

it('C-01 sirve la foto de un beneficiario como imagen', function () {
    $this->actingAs(qaUsuarioDirectorio());
    $bene = qaBene(extra: ['foto' => qaPngDataUri()]);

    $this->get(route('directorio.foto.imagen', ['tipo' => 'beneficiarios', 'id' => $bene->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

// ---------------------------------------------------------------------------
// C-02 · ESCALADA DE PRIVILEGIOS ENTRE MÓDULOS
// ---------------------------------------------------------------------------

it('C-02 no deja leer la foto de un detenido con sólo el permiso de directorio', function () {
    $detenido = Detenido::query()->create([
        'nombre' => 'PEDRO', 'paterno' => 'SILVA', 'foto' => qaPngDataUri(),
    ]);

    $this->actingAs(qaSoloDirectorio());

    // La ruta vive bajo `can:directorio.gestionar` pero el recurso es de
    // Seguridad ciudadana: debería exigir `seguridad.gestionar`.
    $this->get(route('directorio.foto.imagen', ['tipo' => 'detenidos', 'id' => $detenido->id]))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// C-03 · XSS ALMACENADO VÍA FOTO
// ---------------------------------------------------------------------------

it('C-03 no sirve un SVG con script como imagen del directorio', function () {
    $this->actingAs(qaUsuarioDirectorio());

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.cookie)"><script>alert(1)</script></svg>';
    $bene = qaBene(extra: ['foto' => 'data:image/svg+xml;base64,'.base64_encode($svg)]);

    $r = $this->get(route('directorio.foto.imagen', ['tipo' => 'beneficiarios', 'id' => $bene->id]));

    // Debe rechazarse (sólo mapas de bits) o servirse de forma inerte
    // (nosniff + Content-Disposition: attachment).
    expect($r->headers->get('Content-Type'))->not->toContain('svg');
});

it('C-04 marca las imágenes servidas con X-Content-Type-Options: nosniff', function () {
    $this->actingAs(qaUsuarioDirectorio());
    $bene = qaBene(extra: ['foto' => qaPngDataUri()]);

    $this->get(route('directorio.foto.imagen', ['tipo' => 'beneficiarios', 'id' => $bene->id]))
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('C-05 rechaza una foto que no es una imagen en data URI', function () {
    $this->actingAs(qaUsuarioDirectorio());

    $this->post(route('directorio.beneficiarios.store'), [
        'nombre' => 'JUAN',
        'foto' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('foto');
});

it('C-06 limita el tamaño de la foto que se acepta', function () {
    $this->actingAs(qaUsuarioDirectorio());

    // ~4 MB de basura en un campo sin `max:`.
    $this->post(route('directorio.beneficiarios.store'), [
        'nombre' => 'JUAN',
        'foto' => 'data:image/png;base64,'.str_repeat('A', 4 * 1024 * 1024),
    ])->assertSessionHasErrors('foto');
});

// ---------------------------------------------------------------------------
// C-07..C-09 · VALIDACIÓN DE DATOS PERSONALES
// ---------------------------------------------------------------------------

it('C-07 rechaza una CURP con formato inválido', function () {
    $this->actingAs(qaUsuarioDirectorio());

    $this->post(route('directorio.beneficiarios.store'), [
        'nombre' => 'JUAN',
        'curp' => 'NO-ES-UNA-CURP',
    ])->assertSessionHasErrors('curp');
});

it('C-08 rechaza un correo que no es correo', function () {
    $this->actingAs(qaUsuarioDirectorio());

    $this->post(route('directorio.beneficiarios.store'), [
        'nombre' => 'JUAN',
        'correo' => 'esto no es un correo',
    ])->assertSessionHasErrors('correo');
});

it('C-09 rechaza una fecha de nacimiento futura', function () {
    $this->actingAs(qaUsuarioDirectorio());

    $this->post(route('directorio.beneficiarios.store'), [
        'nombre' => 'JUAN',
        'nacimiento' => now()->addYears(5)->toDateString(),
    ])->assertSessionHasErrors('nacimiento');
});

it('C-10 no permite dos beneficiarios con la misma CURP', function () {
    $this->actingAs(qaUsuarioDirectorio());
    $curp = 'PELJ800101HDFRPN09';

    $this->post(route('directorio.beneficiarios.store'), ['nombre' => 'JUAN', 'curp' => $curp]);
    $this->post(route('directorio.beneficiarios.store'), ['nombre' => 'OTRO JUAN', 'curp' => $curp])
        ->assertSessionHasErrors('curp');

    expect(Beneficiario::query()->where('curp', $curp)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// C-11 · FUGA DE DATOS EN EL BUSCADOR
// ---------------------------------------------------------------------------

it('C-11 no devuelve el padrón completo cuando la búsqueda viene vacía', function () {
    $this->actingAs(qaUsuarioDirectorio());
    foreach (range(1, 5) as $i) {
        qaBene("PERSONA{$i}");
    }

    $r = $this->getJson(route('directorio.beneficiarios.buscar'));

    $r->assertOk();
    expect($r->json())->toHaveCount(0);
});

it('C-12 no deja que un comodín SQL LIKE saque todo el padrón', function () {
    $this->actingAs(qaUsuarioDirectorio());
    foreach (range(1, 5) as $i) {
        qaBene("PERSONA{$i}");
    }

    // `%` sin escapar hace match con todo.
    $r = $this->getJson(route('directorio.beneficiarios.buscar', ['busqueda' => '%']));

    $r->assertOk();
    expect($r->json())->toHaveCount(0);
});

// ---------------------------------------------------------------------------
// C-13 · AUTORIZACIÓN SOBRE COMENTARIOS
// ---------------------------------------------------------------------------

it('C-13 no deja borrar el comentario de otro usuario', function () {
    $autor = qaUsuarioDirectorio();
    $bene = qaBene();

    $this->actingAs($autor);
    $this->post(route('directorio.comentarios.store', ['tipo' => 'beneficiarios', 'id' => $bene->id]), [
        'comentario' => 'Comentario del autor',
    ])->assertSessionHasNoErrors();

    $comentario = Comentario::query()->firstOrFail();

    // Otro usuario del módulo intenta borrarlo.
    $this->actingAs(qaUsuarioDirectorio());
    $this->delete(route('directorio.comentarios.destroy', $comentario))
        ->assertForbidden();

    expect(Comentario::query()->whereKey($comentario->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// C-14 · LOOKUPS GEOGRÁFICOS
// ---------------------------------------------------------------------------

it('C-14 acota el número de localidades que devuelve de un solo golpe', function () {
    $this->actingAs(qaUsuarioDirectorio());

    $estado = \App\Models\CatEstado::query()->create(['nombre' => 'HIDALGO']);
    $municipio = \App\Models\CatMunicipio::query()->create([
        'clave' => 48, 'nombre' => 'PACHUCA', 'estado_id' => $estado->id,
    ]);
    foreach (range(1, 60) as $i) {
        \App\Models\CatLocalidad::query()->create([
            'clave' => $i, 'nombre' => "LOCALIDAD {$i}",
            'municipio_id' => $municipio->id, 'estado_id' => $estado->id,
        ]);
    }

    $r = $this->getJson(route('directorio.ubicaciones.localidades', ['municipio_id' => $municipio->id]));

    $r->assertOk();
    expect(count($r->json()))->toBeLessThanOrEqual(50);
});
