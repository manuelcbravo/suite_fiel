<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los ejes y subejes del Plan Municipal ya existían como `cat_ejes` /
 * `cat_subejes` (Módulo 0, administrables desde Catálogos): salen de las
 * mismas tablas legacy `tbl_eje` / `tbl_subeje`.
 *
 * Al migrar Obra pública se crearon `cat_pmd_ejes` / `cat_pmd_subejes` por
 * duplicado. Como conservan los mismos ids, apuntar la obra al catálogo bueno
 * no mueve un solo dato; aquí se repunta y se tiran las copias.
 *
 * Los del Plan Estatal (`cat_ped_*`) sí son nuevos y se quedan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_obras', function (Blueprint $t): void {
            $t->dropForeign(['pmd_eje_id']);
            $t->dropForeign(['pmd_subeje_id']);
            $t->foreign('pmd_eje_id')->references('id')->on('cat_ejes')->nullOnDelete();
            $t->foreign('pmd_subeje_id')->references('id')->on('cat_subejes')->nullOnDelete();
        });

        Schema::dropIfExists('cat_pmd_subejes');
        Schema::dropIfExists('cat_pmd_ejes');
    }

    public function down(): void
    {
        // Se rehacen las copias vacías sólo para poder revertir el FK.
        Schema::create('cat_pmd_ejes', function (Blueprint $t): void {
            $t->id();
            $t->string('nombre');
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('cat_pmd_subejes', function (Blueprint $t): void {
            $t->id();
            $t->string('nombre');
            $t->foreignId('eje_id')->nullable()->constrained('cat_pmd_ejes')->nullOnDelete();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::table('tbl_obras', function (Blueprint $t): void {
            $t->dropForeign(['pmd_eje_id']);
            $t->dropForeign(['pmd_subeje_id']);
        });
    }
};
