<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 4 — Agenda / Calendario: tipos de evento (con color) y eventos.
 *
 * El legacy guardaba fecha y hora en columnas de texto separadas
 * (`fechainicio`+`horainicio`); aquí se consolidan en timestamps `inicio`/`fin`.
 * Se excluyen los tipos "Gira de Trabajo" (giras) por alcance. La geografía
 * del legacy venía vacía (todo 0) y se descarta; el lugar queda en `lugar`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_tipos_evento', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('color', 20)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('tbl_eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fin')->nullable();
            $table->boolean('todo_el_dia')->default(false);
            $table->foreignId('tipo_evento_id')->nullable()->constrained('cat_tipos_evento')->nullOnDelete();
            $table->string('lugar')->nullable();
            $table->string('contacto')->nullable();
            $table->string('telefono')->nullable();
            $table->string('personas')->nullable();
            $table->string('representante')->nullable();
            $table->boolean('asiste')->default(false);
            $table->boolean('confirmado')->default(false);
            $table->boolean('discurso')->default(false);
            $table->boolean('privado')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('inicio');
            $table->index('tipo_evento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_eventos');
        Schema::dropIfExists('cat_tipos_evento');
    }
};
