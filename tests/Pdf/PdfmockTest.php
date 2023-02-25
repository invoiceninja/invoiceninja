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

use Tests\TestCase;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Currency;
use Tests\MockAccountData;
use App\Services\Pdf\PdfMock;
use Beganovich\Snappdf\Snappdf;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;

/**
 * @test
 * @covers  App\Services\Pdf\PdfService
 */
class PdfmockTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

    }

    public function testPdfInstance ()
    {

        $entity = (new \App\Services\Pdf\PdfMock())->build();

        $this->assertInstanceOf(Invoice::class, $entity);
        $this->assertNotNull($entity->client);


        $pdf_service = new PdfService($entity->invitation);

        $this->assertNotNull($pdf_service);

        $pdf_config = (new PdfConfiguration($pdf_service));

        $this->assertNotNull($pdf_config);


    }

    public function testHtmlGeneration()
    {
        $mock = (new PdfMock())->build();

        $pdf_service = new PdfService($mock->invitation);

        $pdf_config = (new PdfConfiguration($pdf_service));
        $pdf_config->entity = $mock;
        $pdf_config->setTaxMap($mock->tax_map);
        $pdf_config->setTotalTaxMap($mock->total_tax_map);
        $pdf_config->setCurrency(Currency::find(1));
        $pdf_config->setCountry(Country::find(840));
        $pdf_config->client = $mock->client;
        $pdf_config->entity_design_id = 'invoice_design_id';
        $pdf_config->settings_object = $mock->client;
        $pdf_config->entity_string = 'invoice';
        $pdf_config->settings = $pdf_config->service->company->settings;

        $this->assertNotNull($pdf_config);
    }
}
