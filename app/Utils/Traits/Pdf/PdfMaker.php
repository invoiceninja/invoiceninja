<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits\Pdf;

use App\Exceptions\InternalPDFFailure;
use Beganovich\Snappdf\Snappdf;
use setasign\Fpdi\PdfParser\StreamReader;

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

        if (config('ninja.snappdf_chromium_path')) {
            $pdf->setChromiumPath(config('ninja.snappdf_chromium_path'));
        }

        if (config('ninja.snappdf_chromium_arguments')) {
            $pdf->clearChromiumArguments();
            $pdf->addChromiumArguments(config('ninja.snappdf_chromium_arguments'));
        }

        $generated  = $pdf
                        ->setHtml($html)
                        ->generate();


       // initiate PDF
        $pdf = new PDF();
 
        // set the source file
        // $pageCount = $pdf->setSourceFile("file-1.pdf");
        $pageCount = $pdf->setSourceFile(StreamReader::createByString($generated));

        $pdf->AliasNbPages();
        for ($i=1; $i <= $pageCount; $i++) { 
            //import a page then get the id and will be used in the template
            $tplId = $pdf->importPage($i);
            //create a page
        
            $templateSize = $pdf->getTemplateSize($tplId);
            $pdf->AddPage('', [$templateSize['width'], $templateSize['height']]);

            // $pdf->AddPage();
            //use the template of the imporated page
            $pdf->useTemplate($tplId);
        }
 
 
        $generated = $pdf->Output();


        if($generated)
            return $generated;


        throw new InternalPDFFailure('There was an issue generating the PDF locally');
    }
}
