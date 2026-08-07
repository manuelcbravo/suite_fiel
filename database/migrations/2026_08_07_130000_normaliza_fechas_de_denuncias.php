<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `fecha_denuncia` y `fecha_atencion` son columnas de texto (así vienen del
 * legacy) y el listado filtra el rango comparando cadenas. Con formatos
 * mezclados —`15/01/2026` junto a `2026-03-01`— el rango no significaba nada:
 * el orden alfabético no es el orden cronológico.
 *
 * A partir de ahora las peticiones normalizan a `Y-m-d`; esta migración pone
 * al día lo que ya estaba guardado.
 */
return new class extends Migration
{
    /** Formatos que aparecen en la captura histórica. */
    private const FORMATOS = ['d/m/Y', 'd-m-Y', 'd/m/y', 'Y/m/d'];

    public function up(): void
    {
        DB::table('tbl_denuncias')
            ->select('id', 'fecha_denuncia', 'fecha_atencion')
            ->orderBy('id')
            ->chunkById(1000, function ($filas): void {
                foreach ($filas as $fila) {
                    $cambios = [];

                    foreach (['fecha_denuncia', 'fecha_atencion'] as $campo) {
                        $normalizada = $this->normalizar($fila->{$campo});

                        if ($normalizada !== null && $normalizada !== $fila->{$campo}) {
                            $cambios[$campo] = $normalizada;
                        }
                    }

                    if ($cambios !== []) {
                        DB::table('tbl_denuncias')->where('id', $fila->id)->update($cambios);
                    }
                }
            });
    }

    /** Devuelve la fecha como `Y-m-d`, o null si no se reconoce el formato. */
    private function normalizar(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null; // Vacía o ya normalizada: no se toca.
        }

        foreach (self::FORMATOS as $formato) {
            $fecha = DateTimeImmutable::createFromFormat('!'.$formato, $valor);

            if ($fecha !== false) {
                return $fecha->format('Y-m-d');
            }
        }

        // Texto libre que no es una fecha: se deja intacto para no perder lo
        // que el capturista escribió.
        return null;
    }

    public function down(): void
    {
        // No se revierte: el formato normalizado es el correcto y no hay
        // registro del original.
    }
};
