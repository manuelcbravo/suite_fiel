<?php

use App\Models\CatTipoIncidencia;
use App\Models\Denuncia;
use App\Models\Detenido;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('genera el PDF de la denuncia con sus tres etapas', function () {
    $this->actingAs(usuarioSeguridad());

    $tipo = CatTipoIncidencia::query()->create(['nombre' => 'RIÑA']);
    $denuncia = Denuncia::query()->create([
        'denunciante_nombre' => 'JOSÉ',
        'denunciante_paterno' => 'PÉREZ',
        'denuncia' => 'Riña en la vía pública con acentos: ñ á é í ó ú',
        'fecha_denuncia' => '2026-03-10',
        'tipo_incidencia_id' => $tipo->id,
        'atendido_por' => 'OFICIAL RAMÍREZ',
        'descripcion_situacion' => 'Se atendió el reporte',
        'conclusion' => 'Sin más que informar',
        'con_atencion' => true,
        'con_termino' => true,
    ]);

    $respuesta = $this->get(route('seguridad.denuncias.pdf', $denuncia));

    $respuesta->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // Un PDF válido siempre empieza con la firma %PDF.
    expect($respuesta->getContent())->toStartWith('%PDF');
});

it('genera el PDF de la ficha del detenido', function () {
    $this->actingAs(usuarioSeguridad());

    $detenido = Detenido::query()->create([
        'nombre' => 'CINDY',
        'paterno' => 'HERNÁNDEZ',
        'materno' => 'ÁNGELES',
        'edad' => 19,
        'sexo' => 1,
        'motivo_retencion' => 'Falta administrativa',
    ]);

    $respuesta = $this->get(route('seguridad.detenidos.pdf', $detenido));

    $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($respuesta->getContent())->toStartWith('%PDF');
});
