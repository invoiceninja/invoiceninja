<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits\Pdf;

use App\Exceptions\InternalPDFFailure;
use Beganovich\Snappdf\Snappdf;

trait PdfMaker
{
    /**
     * Returns a PDF stream.
     *
     * @param  string|null $header Header to be included in PDF
     * @param  string|null $footer Footer to be included in PDF
     * @param  string $html   The HTML object to be converted into PDF
     *
     * @return string        The PDF string
     */
    public function makePdf($header, $footer, $html)
    {
        $pdf = new Snappdf();

        if (config('ninja.snappdf_chromium_arguments')) {
            $pdf->clearChromiumArguments();
            $pdf->addChromiumArguments(config('ninja.snappdf_chromium_arguments'));
        }

        if (config('ninja.snappdf_chromium_path')) {
            $pdf->setChromiumPath(config('ninja.snappdf_chromium_path'));
        }

        $html = str_ireplace(['file:/', 'iframe', '<embed', '&lt;embed', '&lt;object', '<object', '127.0.0.1', 'localhost'], '', $html);

        $generated = $pdf
                        ->setHtml($html)
                        ->generate();

        if ($generated) {
            return $generated;
        }

        throw new InternalPDFFailure('There was an issue generating the PDF locally');
    }
}
