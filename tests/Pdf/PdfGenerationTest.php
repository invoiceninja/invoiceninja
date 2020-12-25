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

namespace Tests\Pdf;

use Beganovich\Snappdf\Snappdf;
use Tests\TestCase;

/**
 * @test
 //@covers  App\DataMapper\BaseSettings
 */
class PdfGenerationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testPdfGeneration()
    {
        $pdf = new Snappdf();

        if (config('ninja.snappdf_chromium_path')) {
            $pdf->setChromiumPath(config('ninja.snappdf_chromium_path'));
        }

        $pdf = $pdf
            ->setHtml('<h1>Invoice Ninja</h1>')
            ->generate();

        $this->assertNotNull($pdf);
    }
}
