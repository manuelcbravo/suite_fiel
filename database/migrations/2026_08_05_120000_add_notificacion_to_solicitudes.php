<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ciudadano notificado" del legacy `seguimiento_add.php`: la casilla se captura
 * al dar seguimiento y el listado la usa para marcar "PENDIENTE NOTIFICACIÓN"
 * en las solicitudes no aprobadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_solicitudes', function (Blueprint $table): void {
            $table->boolean('notificacion')->default(false)->after('control_administrativo');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_solicitudes', function (Blueprint $table): void {
            $table->dropColumn('notificacion');
        });
    }
};
