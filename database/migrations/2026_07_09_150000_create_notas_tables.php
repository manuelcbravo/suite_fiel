<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 5 — Notas: notas (opcionalmente vinculadas a un evento) y sus
 * pendientes tipo checklist (notitas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_notas', function (Blueprint $table): void {
            $table->id();
            $table->text('nota')->nullable();
            $table->date('fecha')->nullable();
            $table->foreignId('evento_id')->nullable()->constrained('tbl_eventos')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('fecha');
        });

        Schema::create('tbl_notitas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nota_id')->constrained('tbl_notas')->cascadeOnDelete();
            $table->string('texto');
            $table->boolean('realizado')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index('nota_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_notitas');
        Schema::dropIfExists('tbl_notas');
    }
};
