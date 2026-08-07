<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La capa electoral que el ETL original dejó fuera.
 *
 * En el legacy la **sección electoral no se captura**: cuelga de la localidad
 * (`tbl_localidad.seccion`, poblada en las 255,909 filas) y el formulario del
 * beneficiario sólo la copia cuando eliges localidad, con el campo en sólo
 * lectura. De ahí que el 99.3% de los beneficiarios la traiga.
 *
 * Se rescata también lo que viene junto en la localidad —distrito federal y
 * tipo de sección (urbana/rural/mixta)— y los cuatro catálogos de
 * clasificación del beneficiario, que están casi vacíos pero son suyos.
 */
return new class extends Migration
{
    /** Los cuatro catálogos de clasificación, todos con la forma id/nombre. */
    private const CATALOGOS = [
        'cat_filiaciones' => 'cat_filiaciones',
        'cat_antagonicos' => 'cat_antagonicos',
        'cat_lideres' => 'cat_lideres',
        'cat_influencias' => 'cat_influencias',
    ];

    public function up(): void
    {
        $conLegacy = $this->hayLegacy();

        Schema::table('cat_localidades', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('seccion')->nullable()->index();
            $tabla->unsignedSmallInteger('distrito_federal')->nullable();
            $tabla->string('tipo_seccion', 20)->nullable();
        });

        foreach (self::CATALOGOS as $tabla => $_) {
            Schema::create($tabla, function (Blueprint $t): void {
                $t->id();
                $t->string('nombre');
                $t->softDeletes();
                $t->timestamps();
            });
        }

        // El padrón de secciones del estado: da el distrito federal y local de
        // cada sección. El legacy lo consulta pero no lo llega a mostrar.
        Schema::create('cat_secciones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->unsignedInteger('seccion')->index();
            $tabla->foreignId('estado_id')->constrained('cat_estados');
            $tabla->foreignId('municipio_id')->nullable()->constrained('cat_municipios');
            $tabla->unsignedSmallInteger('distrito_federal')->nullable();
            $tabla->unsignedSmallInteger('distrito_local')->nullable();
            $tabla->timestamps();
        });

        Schema::table('tbl_beneficiarios', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('seccion')->nullable()->index();
            $tabla->foreignId('filiacion_id')->nullable()->constrained('cat_filiaciones');
            $tabla->foreignId('antagonico_id')->nullable()->constrained('cat_antagonicos');
            $tabla->foreignId('lider_id')->nullable()->constrained('cat_lideres');
            $tabla->foreignId('influencia_id')->nullable()->constrained('cat_influencias');
        });

        if ($conLegacy) {
            $this->rescata();
        }
    }

    public function down(): void
    {
        Schema::table('tbl_beneficiarios', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('filiacion_id');
            $tabla->dropConstrainedForeignId('antagonico_id');
            $tabla->dropConstrainedForeignId('lider_id');
            $tabla->dropConstrainedForeignId('influencia_id');
            $tabla->dropColumn('seccion');
        });

        Schema::dropIfExists('cat_secciones');

        foreach (array_keys(self::CATALOGOS) as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::table('cat_localidades', function (Blueprint $tabla): void {
            $tabla->dropColumn(['seccion', 'distrito_federal', 'tipo_seccion']);
        });
    }

    /** El schema legacy sólo está en la máquina de migración, no en pruebas. */
    private function hayLegacy(): bool
    {
        return DB::getDriverName() === 'pgsql'
            && DB::selectOne("select 1 as hay from information_schema.tables
                where table_schema = 'hgo_pachuca' and table_name = 'tbl_localidad'") !== null;
    }

    private function rescata(): void
    {
        // La localidad se resuelve por clave natural (estado + clave), nunca
        // por el id de nuestra tabla. `clave` es donde el ETL original guardó
        // el id legacy: el par (clave, estado) casa las 255,909 exactas.
        DB::statement("
            update cat_localidades l
               set seccion = nullif(v.seccion, 0),
                   distrito_federal = nullif(v.dtto_fed, 0),
                   tipo_seccion = nullif(trim(v.nom_tipo_secc), '')
              from hgo_pachuca.tbl_localidad v
             where l.clave = v.id
               and l.estado_id = v.id_estado
        ");

        foreach (self::CATALOGOS as $destino => $origen) {
            DB::statement("
                insert into {$destino} (id, nombre, deleted_at, created_at, updated_at)
                select id, nombre,
                       case when borrado = 1 then now() end,
                       now(), now()
                  from hgo_pachuca.{$origen}
            ");
            $this->reiniciaSecuencia($destino);
        }

        DB::statement('
            insert into cat_secciones (seccion, estado_id, municipio_id, distrito_federal, distrito_local, created_at, updated_at)
            select s.seccion, s.id_estado, m.id, nullif(s.dist_fed_id, 0), nullif(s.dist_loc, 0), now(), now()
              from hgo_pachuca.secciones s
              left join cat_municipios m
                     on m.estado_id = s.id_estado and m.clave = s.municipio_id
             where exists (select 1 from cat_estados e where e.id = s.id_estado)
        ');

        // La sección del beneficiario es copia de la de su localidad; se trae
        // tal cual quedó capturada, no se recalcula.
        DB::statement('
            update tbl_beneficiarios b
               set seccion = nullif(v.seccion, 0),
                   filiacion_id = f.id,
                   antagonico_id = a.id,
                   lider_id = li.id,
                   influencia_id = i.id
              from hgo_pachuca.beneficiario v
              left join cat_filiaciones f on f.id = nullif(trim(v.filiacion_id), \'\')::int
              left join cat_antagonicos a on a.id = nullif(v.antagonico_id, 0)
              left join cat_lideres li on li.id = nullif(v.lider_id, 0)
              left join cat_influencias i on i.id = nullif(v.influencia_id, 0)
             where b.id = v.id
        ');
    }

    private function reiniciaSecuencia(string $tabla): void
    {
        DB::statement("
            select setval(pg_get_serial_sequence('{$tabla}', 'id'),
                          coalesce((select max(id) from {$tabla}), 1))
        ");
    }
};
