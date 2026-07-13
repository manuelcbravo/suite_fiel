<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 6 — Invitaciones: invitaciones recibidas (opcionalmente vinculadas
 * a un evento de la agenda) y el registro de correos de notificación.
 *
 * Como en Agenda, fecha+hora legacy (texto) se consolidan en timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_invitaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo')->nullable();
            $table->string('destinatario')->nullable();
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fin')->nullable();
            $table->boolean('todo_el_dia')->default(false);
            $table->foreignId('tipo_evento_id')->nullable()->constrained('cat_tipos_evento')->nullOnDelete();
            $table->foreignId('evento_id')->nullable()->constrained('tbl_eventos')->nullOnDelete();
            $table->string('lugar')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->string('contacto')->nullable();
            $table->string('telefono')->nullable();
            $table->timestamp('fecha_recepcion')->nullable();
            $table->boolean('confirmado')->default(false);
            $table->boolean('atendida')->default(false);
            $table->text('comentario')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('inicio');
        });

        Schema::create('tbl_invitacion_correos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitacion_id')->constrained('tbl_invitaciones')->cascadeOnDelete();
            $table->text('correos'); // lista de destinatarios (separados por coma)
            $table->text('mensaje')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('invitacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_invitacion_correos');
        Schema::dropIfExists('tbl_invitaciones');
    }
};
