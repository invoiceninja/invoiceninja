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

use App\Utils\Traits\Pdf\PDF;
use setasign\Fpdi\PdfParser\StreamReader;

trait PageNumbering
{
    private function pageNumbering($pdf_data_object, $company)
    {
        if (! property_exists($company->settings, 'page_numbering') || ! $company->settings->page_numbering) {
            return $pdf_data_object;
        }

        try {
            $pdf = new PDF();

            $pdf->setAlignment($company->getSetting('page_numbering_alignment'));

            $pageCount = $pdf->setSourceFile(StreamReader::createByString($pdf_data_object));

            $pdf->AliasNbPages();

            for ($i = 1; $i <= $pageCount; $i++) {
                //import a page then get the id and will be used in the template
                $tplId = $pdf->importPage($i);

                //create a page
                $templateSize = $pdf->getTemplateSize($tplId);

                $pdf->AddPage($templateSize['orientation'], [$templateSize['width'], $templateSize['height']]);

                $pdf->useTemplate($tplId);
            }

            return $pdf->Output('S');
        } catch (\Exception $e) {
            nlog($e->getMessage());
        }
    }
}
