<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Pdf;

/**
 * Minimal PDF output for CI and tests (PDF_GENERATOR=simulator).
 */
class SimulatedPdf
{
    private static ?string $blank_page = null;

    /**
     * Returns a cached single-page blank PDF. HTML is ignored.
     */
    public static function generate(string $html = ''): string
    {
        unset($html);

        if (self::$blank_page === null) {
            $pdf = new \FPDF();
            $pdf->AddPage();
            self::$blank_page = $pdf->Output('S');
        }

        return self::$blank_page;
    }
}
