<?php

namespace App\Utils\Traits\Pdf;

use setasign\Fpdi\Fpdi;
 
class PDF extends FPDI
{
 
    function Footer()
    {
        // Position at 1.5 cm from bottom
        // $this->SetY(-7);
        // $this->SetX(-50);

        $this->SetXY(0, -5);

        // Arial italic 8
        $this->SetFont('Arial','I',8);
        // Page number
        $this->Cell(0,5, ctrans('texts.page').' '.$this->PageNo().'/{nb}',0,0,'C');
    }
}