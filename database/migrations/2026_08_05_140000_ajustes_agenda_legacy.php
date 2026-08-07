<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paridad del formulario de evento con el legacy (`calendario_editar.php`):
 *
 *  - `localidad_id`: el legacy pide la localidad del evento aparte del lugar.
 *  - `asiste`: allá es un par de opciones (1 = Presidente, 2 = Representante),
 *    no un sí/no. El campo "Representante" sólo aplica cuando es 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_eventos', function (Blueprint $table): void {
            $table->foreignId('localidad_id')->nullable()->after('lugar')
                ->constrained('cat_localidades')->nullOnDelete();
            $table->unsignedSmallInteger('asiste_opcion')->nullable()->after('asiste');
        });

        // El booleano previo sólo distinguía "asiste alguien": si estaba
        // marcado se asume que fue el representante (el caso que lo usaba).
        DB::table('tbl_eventos')->where('asiste', true)->update(['asiste_opcion' => 2]);

        Schema::table('tbl_eventos', function (Blueprint $table): void {
            $table->dropColumn('asiste');
        });
        Schema::table('tbl_eventos', function (Blueprint $table): void {
            $table->renameColumn('asiste_opcion', 'asiste');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_eventos', function (Blueprint $table): void {
            $table->renameColumn('asiste', 'asiste_opcion');
        });
        Schema::table('tbl_eventos', function (Blueprint $table): void {
            $table->boolean('asiste')->default(false)->after('representante');
        });

        DB::table('tbl_eventos')->whereNotNull('asiste_opcion')->update(['asiste' => true]);

        Schema::table('tbl_eventos', function (Blueprint $table): void {
            $table->dropColumn('asiste_opcion');
            $table->dropConstrainedForeignId('localidad_id');
        });
    }
};
