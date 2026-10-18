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

namespace Tests\Unit\Utils\Pdf;

use App\Utils\Pdf\SimulatedPdf;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class SimulatedPdfTest extends TestCase
{
    public function testGenerateReturnsParseableBlankPdf(): void
    {
        $pdf = SimulatedPdf::generate('<h1>ignored</h1>');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame($pdf, SimulatedPdf::generate('<p>also ignored</p>'));

        $parser = new Fpdi();
        $page_count = $parser->setSourceFile(StreamReader::createByString($pdf));

        $this->assertSame(1, $page_count);
    }
}
