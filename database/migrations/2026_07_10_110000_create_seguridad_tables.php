<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Seguridad ciudadana: denuncias/avisos y detenidos.
 * Datos migrados desde el respaldo de Actopan (`hgo_actopan`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Catálogos de seguridad -------------------------------------
        Schema::create('cat_seg_sectores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cat_origenes_denuncia', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('cat_tipos_incidencia', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cat_niveles_violencia', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        // --- Denuncias / avisos -----------------------------------------
        Schema::create('tbl_denuncias', function (Blueprint $table): void {
            $table->id();
            $table->boolean('anonimo')->default(false);
            $table->string('denunciante_nombre')->nullable();
            $table->string('denunciante_paterno')->nullable();
            $table->string('denunciante_materno')->nullable();
            $table->string('fecha_denuncia')->nullable();
            $table->string('hora_denuncia')->nullable();
            $table->foreignId('origen_denuncia_id')->nullable()->constrained('cat_origenes_denuncia')->nullOnDelete();
            $table->text('denuncia')->nullable();
            $table->text('descripcion_situacion')->nullable();
            $table->foreignId('tipo_incidencia_id')->nullable()->constrained('cat_tipos_incidencia')->nullOnDelete();
            $table->foreignId('nivel_violencia_id')->nullable()->constrained('cat_niveles_violencia')->nullOnDelete();
            $table->foreignId('seg_sector_id')->nullable()->constrained('cat_seg_sectores')->nullOnDelete();
            // Ubicación
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->foreignId('localidad_id')->nullable()->constrained('cat_localidades')->nullOnDelete();
            $table->string('latitud')->nullable();
            $table->string('longitud')->nullable();
            // Atención / seguimiento
            $table->string('atendido_por')->nullable();
            $table->string('fecha_atencion')->nullable();
            $table->string('hora_atencion')->nullable();
            $table->text('acciones')->nullable();
            $table->text('acuerdos_convenios')->nullable();
            $table->text('conclusion')->nullable();
            $table->string('asignado')->nullable();
            $table->string('vehiculo')->nullable();
            $table->unsignedSmallInteger('clasificacion')->nullable();
            // Estado del flujo (recepción → turnado → atención → término).
            $table->unsignedSmallInteger('turnado')->default(0);
            $table->boolean('con_atencion')->default(false);
            $table->boolean('con_termino')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tipo_incidencia_id');
            $table->index('seg_sector_id');
        });

        // --- Personas (maestro de detenidos) ----------------------------
        Schema::create('tbl_personas_detenidas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('paterno')->nullable();
            $table->string('materno')->nullable();
            $table->unsignedSmallInteger('sexo')->nullable();
            $table->date('fecha_nac')->nullable();
            $table->string('nacionalidad')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // --- Detenidos (evento de retención) ----------------------------
        Schema::create('tbl_detenidos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('denuncia_id')->nullable()->constrained('tbl_denuncias')->nullOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained('tbl_personas_detenidas')->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->string('paterno')->nullable();
            $table->string('materno')->nullable();
            $table->string('alias')->nullable();
            $table->unsignedSmallInteger('edad')->nullable();
            $table->date('fecha_nac')->nullable();
            $table->unsignedSmallInteger('sexo')->nullable();
            $table->string('nacionalidad')->nullable();
            $table->string('lugar_nac')->nullable();
            $table->foreignId('ocupacion_id')->nullable()->constrained('cat_ocupaciones')->nullOnDelete();
            $table->foreignId('estado_civil_id')->nullable()->constrained('cat_estados_civiles')->nullOnDelete();
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->string('direccion')->nullable();
            $table->string('celular')->nullable();
            $table->string('telefono')->nullable();
            $table->string('lugar_retencion')->nullable();
            $table->string('fecha_retencion')->nullable();
            $table->string('padre_nombre')->nullable();
            $table->string('madre_nombre')->nullable();
            $table->text('motivo_retencion')->nullable();
            $table->text('descripcion_grafica')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('foto')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('denuncia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_detenidos');
        Schema::dropIfExists('tbl_personas_detenidas');
        Schema::dropIfExists('tbl_denuncias');
        Schema::dropIfExists('cat_niveles_violencia');
        Schema::dropIfExists('cat_tipos_incidencia');
        Schema::dropIfExists('cat_origenes_denuncia');
        Schema::dropIfExists('cat_seg_sectores');
    }
};
