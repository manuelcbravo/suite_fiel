<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 0 — Catálogos fundacionales de la Suite FIEL.
 *
 * Reemplazan a las tablas legacy `tbl_*` / `cat_*` del schema `hgo_pachuca`.
 * Convención de nomenclatura: todos los catálogos llevan prefijo `cat_`
 * (las entidades/transaccionales llevarán `tbl_`).
 * Otras convenciones (ver MIGRACION.md):
 *  - Se conservan los IDs legacy para preservar las FKs de los datos migrados
 *    (excepto municipios/localidades, con id surrogate + clave natural).
 *  - `borrado` (int 0/1) → SoftDeletes (`deleted_at`).
 *  - Se descartan las columnas de tenancy (`id_edo_acceso`, `id_mun_acceso`).
 *  - La columna "nombre" del legacy (estado, municipio, tipo, eje…) se
 *    normaliza a `nombre`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Geográficos -------------------------------------------------
        Schema::create('cat_estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('siglas')->nullable();
            $table->timestamps();
        });

        // La clave legacy de municipio es la clave INEGI DENTRO del estado
        // (no es única a nivel nacional): la PK real es (estado, clave).
        // Se usa un id surrogate y la clave natural queda en (estado_id, clave).
        Schema::create('cat_municipios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('clave'); // clave INEGI dentro del estado (id legacy)
            $table->string('nombre');
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->string('latitud')->nullable();
            $table->string('longitud')->nullable();
            $table->timestamps();
            $table->unique(['estado_id', 'clave']);
        });

        Schema::create('cat_tipos_localidad', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        // Igual que municipio: la clave legacy es única solo dentro de
        // (estado, municipio). Id surrogate + clave natural compuesta.
        Schema::create('cat_localidades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('clave'); // id legacy dentro del municipio
            $table->string('nombre');
            $table->foreignId('municipio_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->unsignedBigInteger('tipo_localidad_id')->nullable();
            $table->string('cp')->nullable();
            $table->string('clave_ine')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['estado_id', 'municipio_id', 'clave']);
            $table->index('nombre');
        });

        // --- Generales / clasificación ----------------------------------
        Schema::create('cat_ocupaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cat_profesiones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('abreviatura')->nullable();
            $table->timestamps();
        });

        Schema::create('cat_estados_civiles', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        // Sectores de población (ADULTOS MAYORES, JÓVENES, MUJERES…). Beneficiarios.
        Schema::create('cat_sectores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();
        });

        // Tipo de organización (EMPRESARIAL, SOCIAL, POLÍTICO, RELIGIOSO).
        Schema::create('cat_sectores_organizacion', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();
        });

        // Unidades de medida de apoyos (BULTO, TONELADA, PIEZA, DINERO…).
        Schema::create('cat_unidades_medida', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('cat_origenes_solicitud', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('cat_origenes_recurso', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->softDeletes();
            $table->timestamps();
        });

        // --- Administrativos (dependencias, áreas, PMD) ------------------
        Schema::create('cat_dependencias', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('responsable')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cat_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->foreignId('dependencia_id')->nullable()->constrained('cat_dependencias')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('dependencia_id');
        });

        // Plan Municipal de Desarrollo: ejes y subejes.
        Schema::create('cat_ejes', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('cat_subejes', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->foreignId('eje_id')->nullable()->constrained('cat_ejes')->nullOnDelete();
            $table->foreignId('estado_id')->nullable()->constrained('cat_estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('cat_municipios')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('eje_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_subejes');
        Schema::dropIfExists('cat_ejes');
        Schema::dropIfExists('cat_areas');
        Schema::dropIfExists('cat_dependencias');
        Schema::dropIfExists('cat_origenes_recurso');
        Schema::dropIfExists('cat_origenes_solicitud');
        Schema::dropIfExists('cat_unidades_medida');
        Schema::dropIfExists('cat_sectores_organizacion');
        Schema::dropIfExists('cat_sectores');
        Schema::dropIfExists('cat_estados_civiles');
        Schema::dropIfExists('cat_profesiones');
        Schema::dropIfExists('cat_ocupaciones');
        Schema::dropIfExists('cat_localidades');
        Schema::dropIfExists('cat_tipos_localidad');
        Schema::dropIfExists('cat_municipios');
        Schema::dropIfExists('cat_estados');
    }
};
