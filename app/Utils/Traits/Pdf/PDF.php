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
    public $x_offset = 0; // New property for X-axis offset

    public function Footer()
    {
        $this->SetFont(
            config('ninja.pdf_page_numbering_font_name'), 
            config('ninja.pdf_page_numbering_font_style'), 
            config('ninja.pdf_page_numbering_font_size')
        );
        $this->SetTextColor(
            config('ninja.pdf_page_numbering_font_color_red'), 
            config('ninja.pdf_page_numbering_font_color_green'), 
            config('ninja.pdf_page_numbering_font_color_blue')
        );

        $trans = ctrans('texts.pdf_page_info', ['current' => $this->PageNo(), 'total' => '{nb}']);

        try {
            $trans = mb_convert_encoding($trans, 'ISO-8859-1', 'UTF-8');
        } catch (\Exception $e) {
        }

        // Set Y position
        $this->SetY(config('ninja.pdf_page_numbering_y_alignment'));
        
        // Calculate X position with offset
        $base_x = config('ninja.pdf_page_numbering_x_alignment');
        
        // Set X position based on alignment
        if ($this->text_alignment == 'L') {
            $this->SetX($base_x+5);
            $cell_width = $this->GetPageWidth();
            $this->Cell($cell_width, 5, $trans, 0, 0, 'L');
        } elseif ($this->text_alignment == 'R') {
            $this->SetX($this->GetPageWidth() - 100 - $base_x);
            $cell_width = 100;
            $this->Cell($cell_width, 5, $trans, 0, 0, 'R');
        } else {
            $this->SetX(0);
            $cell_width = $this->GetPageWidth();
            $this->Cell($cell_width, 5, $trans, 0, 0, 'C');
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
