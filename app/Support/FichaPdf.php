<?php

namespace App\Support;

/**
 * Armador de las actas en PDF (denuncias y detenidos) sobre {@see ActaPdf}.
 *
 * El documento se imprime en blanco y negro, así que la jerarquía la carga el
 * tamaño del texto y no el color: etiquetas chicas en versalitas, valores en
 * cuerpo normal. Nada en gris, que al fotocopiarse se pierde.
 */
class FichaPdf
{
    private ActaPdf $pdf;

    private const ANCHO = ActaPdf::ANCHO;

    public function __construct(
        string $titulo,
        string $folio,
        string $estado,
        ?string $usuario = null,
    ) {
        $this->pdf = new ActaPdf('P', 'mm', 'A4');
        $this->pdf->titulo = $titulo;
        $this->pdf->folio = $folio;
        $this->pdf->estado = $estado;
        $this->pdf->fecha = now()->format('d/m/Y H:i');
        $this->pdf->usuario = $usuario;

        $this->pdf->AliasNbPages();
        $this->pdf->SetMargins(15, 12, 15);
        $this->pdf->SetAutoPageBreak(true, 22);
        $this->pdf->AddPage();
    }

    /** Título de bloque, con su filete encima. */
    public function seccion(string $titulo): self
    {
        $this->pdf->Ln(3);
        $this->pdf->regla();
        $this->pdf->Ln(1.5);
        $this->pdf->SetFont('Helvetica', 'B', 9.5);
        $this->pdf->Cell(self::ANCHO, 5, $this->pdf->texto(mb_strtoupper($titulo)), 0, 1);
        $this->pdf->Ln(1.5);

        return $this;
    }

    /**
     * Pares etiqueta/valor en dos columnas: la etiqueta pequeña arriba y el
     * valor debajo. Los vacíos se omiten, igual que en las fichas del sistema.
     *
     * @param  array<string, string|null>  $datos
     */
    public function datos(array $datos): self
    {
        $columna = self::ANCHO / 2;
        $llenos = array_filter(
            $datos,
            fn (?string $v): bool => $v !== null && trim($v) !== '',
        );

        $i = 0;
        $total = count($llenos);

        foreach ($llenos as $etiqueta => $valor) {
            $izquierda = $i % 2 === 0;
            $x = $izquierda ? 15 : 15 + $columna;
            $y = $this->pdf->GetY();

            $this->pdf->SetXY($x, $y);
            $this->pdf->SetFont('Helvetica', '', 6.5);
            $this->pdf->Cell($columna - 4, 3.5, $this->pdf->texto(mb_strtoupper($etiqueta)), 0, 2);

            $this->pdf->SetFont('Helvetica', '', 10);
            $this->pdf->Cell($columna - 4, 5, $this->pdf->texto($valor), 0, 1);

            // La fila avanza al cerrar el par (o con el último dato suelto).
            if ($izquierda && $i + 1 < $total) {
                $this->pdf->SetXY($x, $y);
            } else {
                $this->pdf->SetY($y + 10.5);
            }

            $i++;
        }

        return $this;
    }

    /**
     * Etiqueta y texto largo a todo lo ancho, uno por bloque.
     *
     * @param  array<string, string|null>  $datos
     */
    public function lista(array $datos): self
    {
        foreach ($datos as $etiqueta => $valor) {
            if ($valor === null || trim($valor) === '') {
                continue;
            }

            $this->pdf->SetFont('Helvetica', '', 6.5);
            $this->pdf->Cell(self::ANCHO, 3.5, $this->pdf->texto(mb_strtoupper($etiqueta)), 0, 1);

            $this->pdf->SetFont('Helvetica', '', 10);
            $this->pdf->MultiCell(self::ANCHO, 5, $this->pdf->texto($valor), 0, 'J');
            $this->pdf->Ln(2);
        }

        return $this;
    }

    /**
     * Cierra el acta. La marca de fin permite notar que falta una hoja cuando
     * el legajo se fotocopia o se desengrapa.
     */
    public function salida(): string
    {
        $this->pdf->Ln(2);
        $this->pdf->regla();
        $this->pdf->Ln(1.5);
        $this->pdf->SetFont('Helvetica', '', 6.5);
        $this->pdf->Cell(self::ANCHO, 4, $this->pdf->texto('FIN DEL ACTA'), 0, 1, 'C');

        return $this->pdf->Output('S');
    }
}
