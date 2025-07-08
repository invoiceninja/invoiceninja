<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits\Pdf;

use setasign\Fpdi\Fpdi;

class PDF extends FPDI
{
    public $text_alignment = 'L';

    public function Footer()
    {
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(135, 135, 135);

        $trans = ctrans('texts.pdf_page_info', ['current' => $this->PageNo(), 'total' => '{nb}']);

        try {
            $trans = mb_convert_encoding($trans, 'ISO-8859-1', 'UTF-8');
        } catch (\Exception $e) {
        }

        // Set Y position
        $this->SetY(config('ninja.pdf_page_numbering_y_alignment'));
        
        // Set X position based on alignment
        if ($this->text_alignment == 'L') {
            $this->SetX(5);
            $this->Cell($this->GetPageWidth() - 10, 5, $trans, 0, 0, 'L');
        } elseif ($this->text_alignment == 'R') {
            $this->SetX(0);
            $this->Cell($this->GetPageWidth(), 5, $trans, 0, 0, 'R');
        } else {
            $this->SetX(0);
            // $this->SetX(config('ninja.pdf_page_numbering_y_alignment')); // 10mm from left edge
            $this->Cell($this->GetPageWidth(), 5, $trans, 0, 0, 'C');
        }
    }

    public function setAlignment($alignment)
    {
        if (in_array($alignment, ['C', 'L', 'R'])) {
            $this->text_alignment = $alignment;
        }

        return $this;
    }
}
