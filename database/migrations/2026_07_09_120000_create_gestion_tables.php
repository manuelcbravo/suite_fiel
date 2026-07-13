<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 3 — Gestión / Solicitudes: catálogos propios (rubros, conceptos,
 * acciones), solicitudes, seguimientos (flujo de turnado/respuesta) y los
 * pivotes M2M rubro/sector.
 *
 * Notas de modelado:
 *  - `solicitante` es polimórfico (beneficiario u organización) según el
 *    legacy `tipo_solicitante` (1=persona, 2=organización).
 *  - `rubro_id` y `sector_id` legacy eran listas CSV → tablas pivote.
 *  - Geografía de respuesta resuelta por clave natural (como en Directorio).
 *  - Se omiten columnas de obra (`obra`, `ctrl_admon`), electorales
 *    (`seccion_resp`) y de tenancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Catálogos de gestión ---------------------------------------
        Schema::create('cat_rubros', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cat_conceptos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cat_acciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        // --- Solicitudes -------------------------------------------------
        Schema::create('tbl_solicitudes', function (Blueprint $table): void {
            $table->id();
            $table->string('folio', 300)->nullable();
            $table->string('folio_sistema', 300)->nullable();
            $table->text('solicitud')->nullable();       // descripción de la solicitud
            $table->string('apoyo')->nullable();
            $table->text('desc_bene')->nullable();
            $table->string('cantidad')->nullable();
            $table->string('monto')->nullable();
            $table->string('num_bene')->nullable();
            $table->unsignedInteger('bene_final')->nullable();

            // Solicitante polimórfico (beneficiario / organización).
            $table->nullableMorphs('solicitante');

            $table->foreignId('concepto_id')->nullable()->constrained('cat_conceptos')->nullOnDelete();
            $table->foreignId('procedencia_id')->nullable()->constrained('cat_origenes_solicitud')->nullOnDelete();
            $table->string('origen')->nullable();          // origen del recurso (texto legacy)

            $table->unsignedSmallInteger('status')->default(0)->comment('0=Capturada,1=Turnada,2=No aprobada,3=Para resolver,4=Respuesta de área,5=Atendida,6=Atención rápida');
            $table->unsignedSmallInteger('prioridad')->nullable();
            $table->unsignedSmallInteger('tipo')->nullable();

            $table->date('fecha_recepcion')->nullable();
            $table->string('fecha_comp')->nullable();

            // Geografía de la respuesta / atención.
            $table->foreignId('estado_resp_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->foreignId('municipio_resp_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->foreignId('localidad_resp_id')->nullable()->constrained('cat_localidades')->nullOnDelete();
            $table->string('folio_resp', 500)->nullable();
            $table->string('fecha_resp')->nullable();

            $table->text('imagen')->nullable();            // legacy: base64
            $table->string('latitud')->nullable();
            $table->string('longitud')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('folio');
        });

        // --- Seguimientos (turnado / respuesta) -------------------------
        Schema::create('tbl_seguimientos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('tbl_solicitudes')->cascadeOnDelete();
            $table->foreignId('dependencia_id')->nullable()->constrained('cat_dependencias')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('cat_areas')->nullOnDelete();
            $table->unsignedSmallInteger('estatus')->nullable();
            $table->text('instruccion')->nullable();
            $table->text('comentario')->nullable();
            $table->text('respuesta')->nullable();
            $table->string('responsable')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->unsignedSmallInteger('avance')->nullable();
            $table->unsignedSmallInteger('estatus_resp')->nullable();
            $table->unsignedBigInteger('respuesta_de_id')->nullable(); // auto-ref (sin FK dura)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('solicitud_id');
            $table->index('respuesta_de_id');
        });

        // --- Pivotes M2M -------------------------------------------------
        Schema::create('tbl_solicitud_rubro', function (Blueprint $table): void {
            $table->foreignId('solicitud_id')->constrained('tbl_solicitudes')->cascadeOnDelete();
            $table->foreignId('rubro_id')->constrained('cat_rubros')->cascadeOnDelete();
            $table->primary(['solicitud_id', 'rubro_id']);
        });

        Schema::create('tbl_solicitud_sector', function (Blueprint $table): void {
            $table->foreignId('solicitud_id')->constrained('tbl_solicitudes')->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('cat_sectores')->cascadeOnDelete();
            $table->primary(['solicitud_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_solicitud_sector');
        Schema::dropIfExists('tbl_solicitud_rubro');
        Schema::dropIfExists('tbl_seguimientos');
        Schema::dropIfExists('tbl_solicitudes');
        Schema::dropIfExists('cat_acciones');
        Schema::dropIfExists('cat_conceptos');
        Schema::dropIfExists('cat_rubros');
    }
};
