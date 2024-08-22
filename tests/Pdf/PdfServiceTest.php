<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Pdf;

use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\PdfService;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Services\Pdf\PdfService
 */
class PdfServiceTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testPdfGeneration()
    {

        if(config('ninja.testvars.travis')) {
            $this->markTestSkipped();
        }

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertNotNull($service->getPdf());

    }

    public function testHtmlGeneration()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsString($service->getHtml());

    }

    public function testInitOfClass()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertInstanceOf(PdfService::class, $service);

    }

    public function testEntityResolution()
    {

        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertInstanceOf(PdfConfiguration::class, $service->config);


    }

    public function testDefaultDesign()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertEquals(2, $service->config->design->id);

    }

    public function testHtmlIsArray()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsArray($service->html_variables);

    }

    public function testTemplateResolution()
    {
        $invitation = $this->invoice->invitations->first();

        $service = (new PdfService($invitation))->boot();

        $this->assertIsString($service->designer->template);

    }

}
