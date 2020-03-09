<?php

namespace Tests\Integration;

use App\Designs\Bold;
use App\Designs\Designer;
use App\Models\Design;
use App\Utils\Traits\MakesInvoiceHtml;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class HtmlGenerationTest extends TestCase
{
    use MockAccountData;
    use MakesInvoiceHtml;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testHtmlOutput()
    {
        $design = Design::find(3);

        $designer = new Designer($this->invoice, $design, $this->invoice->client->getSetting('pdf_variables'), 'invoice');

        $html = $this->generateEntityHtml($designer, $this->invoice);

        $this->assertNotNull($html);
    }


}