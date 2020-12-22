<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits\Pdf;

use Beganovich\Snappdf\Snappdf;

trait PdfMaker
{
    /**
     * Returns a PDF stream.
     *
     * @param  string $header Header to be included in PDF
     * @param  string $footer Footer to be included in PDF
     * @param  string $html   The HTML object to be converted into PDF
     *
     * @return string        The PDF string
     */
    public function makePdf($header, $footer, $html)
    {
        $pdf = new Snappdf();

        return $pdf
            ->setHtml($html)
            ->generate();
    }
}
