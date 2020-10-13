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

use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;

/**
 * @test
 //@covers  App\DataMapper\BaseSettings
 */
class PdfGenerationTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();
    }

    private function makePdf($header, $footer, $html, $pdf)
    {
        Browsershot::html($html)
            ->setNodeBinary(config('ninja.system.node_path'))
            ->setNpmBinary(config('ninja.system.npm_path'))
            //->showBrowserHeaderAndFooter()
            //->headerHtml($header)
            //->footerHtml($footer)
            ->waitUntilNetworkIdle()
            //->margins(10,10,10,10)
            ->noSandbox()
            ->savePdf($pdf);
    }

    public function testPdfGeneration()
    {
        $html = file_get_contents(base_path().'/tests/Pdf/invoice.html');
        $pdf = base_path().'/tests/Pdf/invoice.pdf';

        $header = '<div style="font-size:14px;"<header></header>';

        $footer = ' <div style="font-size:14px;"><footer>
                <span class="pageNumber"></span> / <span class="totalPages"></span>
            </footer></div>';

        $this->makePdf($header, $footer, $html, $pdf);

        $this->assertTrue(file_exists($pdf));

        unlink($pdf);
    }

    public function testPdfGeneration2()
    {
        $html = file_get_contents(base_path().'/tests/Pdf/invoice2.html');
        $pdf = base_path().'/tests/Pdf/invoice2.pdf';

        $header = '<div style="font-size:14px;"<header></header>';

        $footer = ' <div style="font-size:14px;"><footer>
                <span class="pageNumber"></span> / <span class="totalPages"></span>
            </footer></div>';

        $this->makePdf($header, $footer, $html, $pdf);

        $this->assertTrue(file_exists($pdf));

        unlink($pdf);
    }

    public function testPdfGeneration3()
    {
        $html = file_get_contents(base_path().'/tests/Pdf/invoice3.html');
        $pdf = base_path().'/tests/Pdf/invoice3.pdf';

        $header = '<div style="font-size:14px;"<header></header>';

        $footer = ' <div style="font-size:14px;"><footer>
                <span class="pageNumber"></span> / <span class="totalPages"></span>
            </footer></div>';

        $this->makePdf($header, $footer, $html, $pdf);

        $this->assertTrue(file_exists($pdf));

        unlink($pdf);
    }

    public function testPdfGeneration4()
    {
        $html = file_get_contents(base_path().'/tests/Pdf/invoice4.html');
        $pdf = base_path().'/tests/Pdf/invoice4.pdf';

        $header = '<div style="font-size:14px;"<header></header>';

        $footer = ' <div style="font-size:14px;"><footer>
                <span class="pageNumber"></span> / <span class="totalPages"></span>
            </footer></div>';

        $this->makePdf($header, $footer, $html, $pdf);

        $this->assertTrue(file_exists($pdf));

        unlink($pdf);
    }

    public function testPdfGeneration5()
    {
        $html = file_get_contents(base_path().'/tests/Pdf/invoice5.html');
        $pdf = base_path().'/tests/Pdf/invoice5.pdf';

        $header = '<div style="font-size:14px;"<header></header>';

        $footer = ' <div style="font-size:14px;"><footer>
                <span class="pageNumber"></span> / <span class="totalPages"></span>
            </footer></div>';

        $this->makePdf($header, $footer, $html, $pdf);

        $this->assertTrue(file_exists($pdf));

        unlink($pdf);
    }
}
