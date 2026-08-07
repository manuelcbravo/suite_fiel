<?php

namespace App\Support;

use Codedge\Fpdf\Fpdf\Fpdf;

/**
 * Hoja base de las actas impresas (denuncias y fichas de detenido).
 *
 * Está pensada para papel: se imprime, se fotocopia y se archiva. De ahí las
 * decisiones — nada de texto gris (se pierde al fotocopiar), folio repetido en
 * cada página por si una hoja se separa del legajo, y numeración "1 de N".
 */
class ActaPdf extends Fpdf
{
    /** Ancho útil con márgenes de 15 mm. */
    public const ANCHO = 180;

    public string $titulo = '';

    public string $folio = '';

    /** Etapa o estatus del expediente, dentro del bloque de folio. */
    public string $estado = '';

    public string $fecha = '';

    public ?string $usuario = null;

    public function Header(): void // phpcs:ignore
    {
        $primera = $this->PageNo() === 1;

        if ($primera) {
            $this->cabeceraCompleta();

            return;
        }

        // Continuación: basta con saber de qué expediente es esta hoja.
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(self::ANCHO / 2, 5, $this->texto($this->titulo), 0, 0);
        $this->SetFont('Courier', '', 8);
        $this->Cell(self::ANCHO / 2, 5, $this->texto($this->folio.' (continuación)'), 0, 1, 'R');
        $this->regla();
        $this->Ln(5);
    }

    private function cabeceraCompleta(): void
    {
        $y = $this->GetY();

        // El bloque de folio va primero: es lo que se lee al hojear un legajo.
        $this->SetXY(15 + self::ANCHO - 62, $y);
        $this->SetDrawColor(0);
        $this->SetLineWidth(0.4);
        $this->Rect(15 + self::ANCHO - 62, $y, 62, 18);
        $this->SetLineWidth(0.2);

        $this->SetXY(15 + self::ANCHO - 59, $y + 2);
        $this->SetFont('Helvetica', '', 6.5);
        $this->Cell(56, 3, $this->texto('FOLIO'), 0, 2);
        $this->SetFont('Courier', 'B', 13);
        $this->Cell(56, 6, $this->texto($this->folio), 0, 2);
        $this->SetFont('Helvetica', '', 6.5);
        $this->Cell(56, 3, $this->texto('ETAPA'), 0, 2);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->Cell(56, 4, $this->texto(mb_strtoupper($this->estado)), 0, 2);

        // Y el título a la izquierda, alineado al mismo tope.
        $this->SetXY(15, $y + 2);
        $this->SetFont('Helvetica', 'B', 15);
        $this->MultiCell(self::ANCHO - 68, 7, $this->texto($this->titulo), 0, 'L');

        $this->SetXY(15, $y + 20);
        $this->regla();
        $this->Ln(5);
    }

    public function Footer(): void // phpcs:ignore
    {
        $this->SetY(-16);
        $this->regla();
        $this->Ln(1.5);

        $this->SetFont('Helvetica', '', 7.5);
        $pie = 'Impreso el '.$this->fecha.($this->usuario ? ' por '.$this->usuario : '');
        $this->Cell(self::ANCHO / 2, 4, $this->texto($pie), 0, 0);

        $this->SetFont('Courier', '', 7.5);
        $this->Cell(
            self::ANCHO / 2,
            4,
            $this->texto('Página '.$this->PageNo().' de {nb}'),
            0,
            0,
            'R',
        );
    }

    /** Filete de separación, en gris medio (se fotocopia sin ensuciarse). */
    public function regla(): void
    {
        $this->SetDrawColor(150);
        $y = $this->GetY();
        $this->Line(15, $y, 15 + self::ANCHO, $y);
        $this->SetDrawColor(0);
    }

    /** FPDF escribe en ISO-8859-1; sin esto los acentos salen rotos. */
    public function texto(?string $valor): string
    {
        return (string) iconv('UTF-8', 'ISO-8859-1//TRANSLIT', trim((string) $valor));
    }
}
