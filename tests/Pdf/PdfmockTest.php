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
use App\Models\Design;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Currency;
use App\Services\Pdf\PdfBuilder;
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

        $entity = (new \App\Services\Pdf\PdfMock(Invoice::class))->build();

        $this->assertInstanceOf(Invoice::class, $entity);
        $this->assertNotNull($entity->client);


        $pdf_service = new PdfService($entity->invitation);

        $this->assertNotNull($pdf_service);

        $pdf_config = (new PdfConfiguration($pdf_service));

        $this->assertNotNull($pdf_config);


    }

    public function testHtmlGeneration()
    {
        $pdf_mock = (new PdfMock(Invoice::class));
        $mock = $pdf_mock->build();

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
        $pdf_config->settings = (object)$pdf_config->service->company->settings;
        $pdf_config->setPdfVariables();
        $pdf_config->design = Design::find(2);
        $pdf_config->currency_entity = $mock->client;
        
        $pdf_service->config = $pdf_config;

        $pdf_designer = (new \App\Services\Pdf\PdfDesigner($pdf_service))->build();
        $pdf_service->designer = $pdf_designer;

        $pdf_service->html_variables = $pdf_mock->getStubVariables();

        $pdf_builder = (new PdfBuilder($pdf_service))->build();
        $pdf_service->builder = $pdf_builder;
        $this->assertNotNull($pdf_config);

        $html = $pdf_service->getHtml();

        $this->assertNotNull($html);
    }

}
