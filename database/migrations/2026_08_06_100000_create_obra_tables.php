<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Obra pública (Módulo 10).
 *
 * El legacy guarda todo en **una sola tabla `obra` de 313 columnas**, con las
 * siete etapas del expediente pegadas una tras otra. Aquí se parte en:
 *
 *  - `tbl_obras`            identificación y ficha técnica;
 *  - una tabla 1:1 por etapa (autorización, adjudicación, ejecución, cierre);
 *  - `tbl_obra_documentos`  los **87 pares `documento` / `obs_documento`** del
 *    legacy, que son en realidad una lista de acuse: qué papel está y qué se
 *    anotó. Colapsa 174 columnas en cinco;
 *  - las tablas hijas que el legacy ya tenía sueltas (componentes, beneficios,
 *    costos y estimaciones).
 *
 * Los importes y cantidades se conservan como texto porque así están en el
 * legacy (`monto_inv`, `monto_contrato`, `num_benef` son `varchar` con datos
 * sucios); convertirlos a numérico aquí perdería registros.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Catálogos propios de obra ----------------------------------
        // Los del Plan Municipal ya existen como `cat_ejes` / `cat_subejes`
        // (Módulo 0); aquí sólo hacen falta los del Plan Estatal.
        Schema::create('cat_ped_ejes', function (Blueprint $t): void {
            $t->id();
            $t->string('nombre');
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('cat_ped_subejes', function (Blueprint $t): void {
            $t->id();
            $t->string('nombre');
            $t->foreignId('eje_id')->nullable()->constrained('cat_ped_ejes')->nullOnDelete();
            $t->softDeletes();
            $t->timestamps();
        });

        // --- Núcleo: identificación y ficha técnica ----------------------
        Schema::create('tbl_obras', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('solicitud_id')->nullable()->constrained('tbl_solicitudes')->nullOnDelete();
            $t->string('num_obra')->nullable();
            $t->text('nombre')->nullable();
            // El legacy lo llama `tipo_obra_id`, pero apunta a `tbl_tipo`, que es el
            // catálogo de unidades de medida de la meta (KILÓMETRO, M2, PIEZA…).
            $t->foreignId('unidad_medida_id')->nullable()->constrained('cat_unidades_medida')->nullOnDelete();
            $t->foreignId('concepto_id')->nullable()->constrained('cat_conceptos')->nullOnDelete();
            $t->foreignId('sector_id')->nullable()->constrained('cat_sectores')->nullOnDelete();
            $t->string('dependencia')->nullable();
            $t->string('prop_financ')->nullable();
            $t->string('monto_inv')->nullable();
            $t->string('num_benef')->nullable();
            $t->text('descripcion')->nullable();
            $t->text('metas')->nullable();

            // Ubicación
            $t->string('calle_num')->nullable();
            $t->foreignId('localidad_id')->nullable()->constrained('cat_localidades')->nullOnDelete();
            $t->text('referencia_mapa')->nullable();
            $t->string('latitud')->nullable();
            $t->string('longitud')->nullable();
            $t->text('col_beneficiados')->nullable();

            // Alineación a los planes de desarrollo
            $t->foreignId('pmd_eje_id')->nullable()->constrained('cat_ejes')->nullOnDelete();
            $t->foreignId('pmd_subeje_id')->nullable()->constrained('cat_subejes')->nullOnDelete();
            $t->foreignId('ped_eje_id')->nullable()->constrained('cat_ped_ejes')->nullOnDelete();
            $t->foreignId('ped_subeje_id')->nullable()->constrained('cat_ped_subejes')->nullOnDelete();

            // Análisis de la ficha técnica
            $t->text('analisis_problematica')->nullable();
            $t->text('analisis_sinobra')->nullable();
            $t->text('alternativa_solucion')->nullable();
            $t->text('asp_tecnicos')->nullable();
            $t->text('asp_ambientales')->nullable();
            $t->text('asp_legales')->nullable();
            $t->text('asp_sociales')->nullable();
            $t->text('rentab_social')->nullable();
            $t->text('impacto_social')->nullable();
            $t->text('descrip_renta_impac')->nullable();
            $t->text('factibilidad')->nullable();
            $t->text('conclusion')->nullable();

            // Los siete acuses de etapa terminada del legacy (`*_completo`).
            foreach ([
                'ficha', 'expediente', 'autorizacion', 'adjudicacion',
                'ejecucion', 'conclusion', 'entrega',
            ] as $etapa) {
                $t->boolean("{$etapa}_completo")->default(false);
            }

            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->softDeletes();
            $t->timestamps();

            $t->index('num_obra');
            $t->index('solicitud_id');
        });

        /**
         * Un renglón por papel del expediente. `etapa` dice en qué pantalla
         * vive y `clave` conserva el nombre de la columna legacy, que es lo
         * que identifica el documento.
         */
        Schema::create('tbl_obra_documentos', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('obra_id')->constrained('tbl_obras')->cascadeOnDelete();
            $t->string('etapa', 20);
            $t->string('clave', 60);
            // Tres estados, como el legacy: "si", "no" y sin capturar.
            $t->boolean('presente')->nullable();
            $t->text('observacion')->nullable();
            $t->timestamps();

            $t->unique(['obra_id', 'clave']);
            $t->index(['obra_id', 'etapa']);
        });

        // --- Autorización -------------------------------------------------
        Schema::create('tbl_obra_autorizacion', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('obra_id')->unique()->constrained('tbl_obras')->cascadeOnDelete();
            $t->string('num_oficio_envio')->nullable();
            $t->date('fecha_oficio_envio')->nullable();
            $t->string('recep_oficio_envio')->nullable();
            $t->string('num_oficio_aut')->nullable();
            $t->date('fecha_oficio_aut')->nullable();
            $t->string('recep_oficio_aut')->nullable();
            $t->string('num_obra_oficio_aut')->nullable();
            $t->string('mod_ejecucion')->nullable();
            $t->string('tipo_contratacion')->nullable();
            $t->text('nombre_obra_aut')->nullable();
            $t->date('inicio_obra_aut')->nullable();
            $t->date('termino_obra_aut')->nullable();
            $t->text('descrip_obra_aut')->nullable();
            $t->string('finan_prog')->nullable();
            $t->string('finan_subprog')->nullable();
            $t->string('finan_costo')->nullable();
            $t->string('finan_estruc')->nullable();
            $t->string('metas_unidad')->nullable();
            $t->string('metas_cantidad')->nullable();
            $t->string('metas_benef')->nullable();
            $t->string('ejercicio_fiscal')->nullable();
            $t->string('ramo')->nullable();
            $t->string('fondo')->nullable();
            $t->string('aporte_fed')->nullable();
            $t->string('aporte_est')->nullable();
            $t->string('aporte_mun')->nullable();
            $t->string('aporte_bene')->nullable();
            $t->string('aporte_otr')->nullable();
            $t->string('presupuesto')->nullable();
            $t->string('anio_prog_anual')->nullable();
            $t->timestamps();
        });

        // --- Adjudicación y contrato --------------------------------------
        Schema::create('tbl_obra_adjudicacion', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('obra_id')->unique()->constrained('tbl_obras')->cascadeOnDelete();
            $t->string('num_licitacion')->nullable();
            $t->string('num_oficio_contrato')->nullable();
            $t->date('fecha_oficio_contrato')->nullable();
            $t->string('convocatoria')->nullable();
            $t->string('num_convocatoria')->nullable();
            $t->date('fecha_convocatoria')->nullable();
            $t->text('medios_convocatoria')->nullable();
            $t->string('costo_bases')->nullable();

            // La contratista es un proveedor del directorio; el legacy además
            // repetía sus datos como texto y se conservan tal cual.
            $t->foreignId('empresa_id')->nullable()->constrained('tbl_proveedores')->nullOnDelete();
            $t->string('nom_empresa')->nullable();
            $t->string('rfc_empresa')->nullable();
            $t->text('dir_empresa')->nullable();
            $t->string('rep_empresa')->nullable();
            $t->string('reg_empresa')->nullable();

            $t->string('num_fianza')->nullable();
            $t->string('monto_fianza')->nullable();
            $t->string('num_cumplimiento')->nullable();
            $t->string('monto_cumplimiento')->nullable();
            $t->string('num_contrato')->nullable();
            $t->date('fecha_contrato')->nullable();
            $t->string('monto_contrato')->nullable();
            $t->string('monto_contrato_original')->nullable();
            $t->date('fecha_inicio_obra')->nullable();
            $t->date('fecha_termino_obra')->nullable();
            $t->date('fecha_inicio_original')->nullable();
            $t->date('fecha_termino_original')->nullable();
            $t->string('num_convenio')->nullable();
            $t->date('fecha_convenio')->nullable();
            $t->timestamps();
        });

        // --- Ejecución -----------------------------------------------------
        Schema::create('tbl_obra_ejecucion', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('obra_id')->unique()->constrained('tbl_obras')->cascadeOnDelete();
            $t->date('fecha_inicio_real')->nullable();
            $t->date('fecha_termino_real')->nullable();
            $t->string('responsable')->nullable();
            $t->date('fecha_orden_inicio')->nullable();
            $t->date('fecha_termino_orden')->nullable();
            $t->date('fecha_entrega_inmueble')->nullable();
            $t->date('fecha_apertura_bitacora')->nullable();
            $t->string('tipo_bitacora')->nullable();
            $t->string('residente')->nullable();
            $t->string('superintendente')->nullable();
            $t->text('observaciones')->nullable();
            $t->string('anticipo')->nullable();
            $t->string('num_factura')->nullable();
            $t->string('monto_factura')->nullable();
            $t->date('fecha_factura')->nullable();
            $t->string('monto_pagado')->nullable();
            $t->date('fecha_pagado')->nullable();
            $t->timestamps();
        });

        // --- Cierre: conclusión y entrega-recepción -------------------------
        Schema::create('tbl_obra_cierre', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('obra_id')->unique()->constrained('tbl_obras')->cascadeOnDelete();
            $t->date('fecha_aviso_conclusion')->nullable();
            $t->string('revision_trabajos')->nullable();
            $t->string('acta_entrega_fisica')->nullable();
            $t->date('fecha_acta_entrega_fisica')->nullable();
            $t->string('finiquito')->nullable();
            $t->text('observaciones_conclusion')->nullable();
            $t->date('fecha_fianza_vicios')->nullable();
            $t->date('fecha_invitacion_acto')->nullable();
            $t->date('fecha_rev_fisica_doc')->nullable();
            $t->text('observacion_general')->nullable();
            $t->string('acta_entrega_recepcion')->nullable();
            $t->date('fecha_acta_entrega_recepcion')->nullable();
            $t->date('fecha_acta_extincion')->nullable();
            $t->text('observaciones_entrega')->nullable();
            $t->timestamps();
        });

        // --- Tablas hijas que el legacy ya tenía sueltas ---------------------
        Schema::create('tbl_obra_componentes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('obra_id')->constrained('tbl_obras')->cascadeOnDelete();
            $t->string('componente')->nullable();
            $t->text('descripcion')->nullable();
            $t->string('cantidad')->nullable();
            $t->string('costo')->nullable();
            $t->string('total')->nullable();
            $t->timestamps();
        });

        foreach (['beneficios', 'costos'] as $tabla) {
            Schema::create("tbl_obra_{$tabla}", function (Blueprint $t): void {
                $t->id();
                $t->foreignId('obra_id')->constrained('tbl_obras')->cascadeOnDelete();
                $t->string('tipo')->nullable();
                $t->text('descripcion')->nullable();
                $t->string('cuantificacion')->nullable();
                $t->string('valoracion')->nullable();
                $t->string('periodicidad')->nullable();
                $t->timestamps();
            });
        }

        Schema::create('tbl_obra_estimaciones', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('obra_id')->constrained('tbl_obras')->cascadeOnDelete();
            $t->string('num_estimacion')->nullable();
            $t->integer('tipo')->nullable();
            $t->string('superintendente')->nullable();
            $t->string('residente')->nullable();
            $t->date('fecha_recepcion')->nullable();
            $t->date('periodo_inicio')->nullable();
            $t->date('periodo_termino')->nullable();
            $t->string('monto')->nullable();
            $t->string('calidad_obra')->nullable();
            $t->string('estatus')->nullable();
            $t->string('monto_aprobado')->nullable();
            $t->date('fecha_aprobacion')->nullable();
            $t->string('num_factura')->nullable();
            $t->date('fecha_factura')->nullable();
            $t->string('importe_pagado')->nullable();
            $t->date('fecha_pago')->nullable();
            $t->string('avance_fisico')->nullable();
            $t->string('avance_financiero')->nullable();
            $t->boolean('completo')->default(false);
            $t->softDeletes();
            $t->timestamps();

            $t->index('obra_id');
        });
    }

    public function down(): void
    {
        foreach ([
            'tbl_obra_estimaciones', 'tbl_obra_costos', 'tbl_obra_beneficios',
            'tbl_obra_componentes', 'tbl_obra_cierre', 'tbl_obra_ejecucion',
            'tbl_obra_adjudicacion', 'tbl_obra_autorizacion', 'tbl_obra_documentos',
            'tbl_obras', 'cat_ped_subejes', 'cat_ped_ejes',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
