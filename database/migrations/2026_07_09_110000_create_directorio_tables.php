<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 2 — Directorio: beneficiarios, organizaciones, proveedores y
 * comentarios (polimórficos).
 *
 * Convenciones (ver MIGRACION.md): prefijo `tbl_` para entidades; `borrado`
 * → SoftDeletes; `id_usuario_act` → `created_by`; se descartan columnas de
 * tenancy y los campos electorales (filiación, antagónico, líder, influencia,
 * sección, militancia).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Beneficiarios (personas físicas/morales del padrón) ---------
        Schema::create('tbl_beneficiarios', function (Blueprint $table): void {
            $table->id();
            // Identidad
            $table->string('nombre')->nullable();
            $table->string('paterno')->nullable();
            $table->string('materno')->nullable();
            $table->string('alias')->nullable();
            $table->string('curp')->nullable();
            $table->unsignedSmallInteger('genero')->nullable();
            $table->date('nacimiento')->nullable();
            $table->unsignedSmallInteger('tipo')->nullable()->comment('1=persona física, 2=moral');
            $table->foreignId('estado_civil_id')->nullable()->constrained('cat_estados_civiles')->nullOnDelete();
            // Domicilio
            $table->string('calle')->nullable();
            $table->string('num_ext')->nullable();
            $table->string('num_int')->nullable();
            $table->string('colonia')->nullable();
            $table->string('cp')->nullable();
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->foreignId('localidad_id')->nullable()->constrained('cat_localidades')->nullOnDelete();
            // Contacto
            $table->string('telefono')->nullable();
            $table->string('celular')->nullable();
            $table->string('celular2')->nullable();
            $table->string('correo')->nullable();
            $table->string('correo2')->nullable();
            $table->string('facebook')->nullable();
            $table->string('twitter')->nullable();
            // Laboral
            $table->string('empresa')->nullable();
            $table->string('puesto')->nullable();
            $table->string('tel_empresa')->nullable();
            $table->foreignId('ocupacion_id')->nullable()->constrained('cat_ocupaciones')->nullOnDelete();
            $table->foreignId('profesion_id')->nullable()->constrained('cat_profesiones')->nullOnDelete();
            $table->string('ocupacion_texto')->nullable();
            // Clasificación / vínculos
            $table->foreignId('sector_id')->nullable()->constrained('cat_sectores')->nullOnDelete();
            $table->string('grupo')->nullable();
            $table->string('vinculo_municipal')->nullable();
            $table->string('vinculo_estatal')->nullable();
            $table->string('vinculo_federal')->nullable();
            // Asistente
            $table->string('asist_nombre')->nullable();
            $table->string('asist_movil')->nullable();
            $table->string('asist_correo')->nullable();
            // Cónyuge
            $table->string('conyuge_nombre')->nullable();
            $table->string('conyuge_movil')->nullable();
            $table->date('conyuge_nacimiento')->nullable();
            // Otros
            $table->text('foto')->nullable(); // legacy: imagen en base64
            $table->unsignedSmallInteger('estatus')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('nombre');
            $table->index('curp');
            $table->index(['estado_id', 'municipio_id']);
        });

        // --- Organizaciones ----------------------------------------------
        Schema::create('tbl_organizaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->unsignedSmallInteger('tipo')->nullable();
            $table->foreignId('sector_organizacion_id')->nullable()->constrained('cat_sectores_organizacion')->nullOnDelete();
            $table->foreignId('representante_id')->nullable()->constrained('tbl_beneficiarios')->nullOnDelete();
            // Domicilio
            $table->string('calle')->nullable();
            $table->string('num_ext')->nullable();
            $table->string('num_int')->nullable();
            $table->string('colonia')->nullable();
            $table->string('cp')->nullable();
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->foreignId('localidad_id')->nullable()->constrained('cat_localidades')->nullOnDelete();
            // Contacto
            $table->string('telefono')->nullable();
            $table->string('celular')->nullable();
            $table->string('correo')->nullable();
            $table->text('foto')->nullable(); // legacy: imagen en base64
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('nombre');
        });

        // --- Proveedores -------------------------------------------------
        Schema::create('tbl_proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('rfc')->nullable();
            $table->string('rep_legal')->nullable();
            $table->string('especialidad')->nullable();
            $table->unsignedSmallInteger('tipo')->nullable();
            $table->unsignedSmallInteger('calificacion')->nullable();
            $table->string('num_prov_gob')->nullable();
            // Domicilio
            $table->string('calle')->nullable();
            $table->string('num_ext')->nullable();
            $table->string('num_int')->nullable();
            $table->string('colonia')->nullable();
            $table->string('cp')->nullable();
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->foreignId('localidad_id')->nullable()->constrained('cat_localidades')->nullOnDelete();
            // Contacto
            $table->string('telefono')->nullable();
            $table->string('celular')->nullable();
            $table->string('correo')->nullable();
            $table->text('foto')->nullable(); // legacy: imagen en base64
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('nombre');
        });

        // --- Comentarios (polimórfico: beneficiario/organizacion/proveedor) --
        Schema::create('tbl_comentarios', function (Blueprint $table): void {
            $table->id();
            $table->morphs('comentable'); // comentable_type + comentable_id
            $table->text('comentario');
            $table->unsignedSmallInteger('tipo')->nullable();
            $table->unsignedSmallInteger('quien')->nullable(); // solo comentarios de organización (legacy)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_comentarios');
        Schema::dropIfExists('tbl_proveedores');
        Schema::dropIfExists('tbl_organizaciones');
        Schema::dropIfExists('tbl_beneficiarios');
    }
};
