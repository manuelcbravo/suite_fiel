<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre de brechas de datos detectadas en la auditoría:
 *  - `control_administrativo` en solicitudes (legacy `ctrl_admon`).
 *  - `representante_id` en proveedores (legacy `rep_legal` = id de beneficiario).
 *  - `tbl_verificaciones` (legacy `tbl_verificar`): verificación/satisfacción
 *    de solicitudes atendidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_solicitudes', function (Blueprint $table): void {
            $table->boolean('control_administrativo')->default(false)->after('tipo');
        });

        Schema::table('tbl_proveedores', function (Blueprint $table): void {
            $table->foreignId('representante_id')->nullable()->after('rep_legal')
                ->constrained('tbl_beneficiarios')->nullOnDelete();
        });

        Schema::create('tbl_verificaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('tbl_solicitudes')->cascadeOnDelete();
            $table->date('fecha')->nullable();
            $table->unsignedSmallInteger('atendido')->nullable()->comment('0=no, 1=sí atendida');
            $table->unsignedSmallInteger('satisfecho')->nullable()->comment('1=Sí, 2=Poco, 3=Nada');
            $table->text('comentario')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('solicitud_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_verificaciones');
        Schema::table('tbl_proveedores', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('representante_id');
        });
        Schema::table('tbl_solicitudes', function (Blueprint $table): void {
            $table->dropColumn('control_administrativo');
        });
    }
};
