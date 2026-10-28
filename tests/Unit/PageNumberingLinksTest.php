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

namespace Tests\Unit;

use App\Utils\Traits\Pdf\PageNumbering;
use Tests\TestCase;

class PageNumberingLinksTest extends TestCase
{
    use PageNumbering;

    private function sourcePdf(): string
    {
        $pdf = new \FPDF();
        $pdf->SetFont('Arial', '', 12);

        $pdf->AddPage();
        $pdf->Cell(0, 10, 'Pay now', 0, 1, 'L', false, 'https://example.com/pay');

        $pdf->AddPage();
        $pdf->Cell(0, 10, 'View invoice', 0, 1, 'L', false, 'https://example.com/view');

        return $pdf->Output('S');
    }

    private function company(): object
    {
        $settings = new \stdClass();
        $settings->page_numbering = true;

        return new class($settings) {
            public function __construct(public object $settings)
            {
            }

            public function getSetting(string $key)
            {
                return 'C';
            }
        };
    }

    public function testPageNumberingKeepsExternalLinks(): void
    {
        $source = $this->sourcePdf();

        $this->assertSame(2, preg_match_all('#/Subtype\s*/Link#', $source));

        $output = $this->pageNumbering($source, $this->company());

        $this->assertSame(2, preg_match_all('#/Subtype\s*/Link#', $output));
        // one link annotation on each of the two pages
        $this->assertSame(2, preg_match_all('#/Type\s*/Page\b.{0,300}?/Annots#s', $output));
        $this->assertStringContainsString('https://example.com/pay', $output);
        $this->assertStringContainsString('https://example.com/view', $output);
    }

    public function testPageNumberingIsSkippedWhenDisabled(): void
    {
        $company = $this->company();
        $company->settings->page_numbering = false;

        $source = $this->sourcePdf();

        $this->assertSame($source, $this->pageNumbering($source, $company));
    }
}
