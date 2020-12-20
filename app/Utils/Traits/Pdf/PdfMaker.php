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

use Beganovich\ChromiumPdf\ChromiumPdf;
use Spatie\Browsershot\Browsershot;

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
        if (config('ninja.experimental_pdf_engine')) {
            $pdf = new ChromiumPdf();

            return $pdf
                ->setChromiumPath(config('ninja.experimental_pdf_engine_chromium_path'))
                ->setHtml($html)
                ->generate();
        }

        $browser = Browsershot::html($html);

        if (config('ninja.system.node_path')) {
            $browser->setNodeBinary(config('ninja.system.node_path'));
        }

        if (config('ninja.system.npm_path')) {
            $browser->setNpmBinary(config('ninja.system.npm_path'));
        }

        return $browser->deviceScaleFactor(1)
                ->showBackground()
                ->deviceScaleFactor(1)
                ->waitUntilNetworkIdle(true)
                ->noSandbox()
                ->ignoreHttpsErrors()
                ->pdf();
    }
}
