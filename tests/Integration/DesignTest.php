<?php

namespace Tests\Integration;

use App\Designs\Designer;
use App\Designs\Modern;
use App\Jobs\Invoice\CreateInvoicePdf;
use App\Jobs\Quote\CreateQuotePdf;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Designs\Designer
 */
class DesignTest extends TestCase
{
  	use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceDesignExists()
    {

    	$modern = new Modern();

    	$designer = new Designer($modern, $this->company->settings->pdf_variables, 'quote');

    	$html = $designer->build($this->invoice)->getHtml();

    	$this->assertNotNull($html);

    	//\Log::error($html);

    	$settings = $this->invoice->client->settings;
    	$settings->invoice_design_id = "9";

    	$this->client->settings = $settings;
    	$this->client->save();

    	CreateInvoicePdf::dispatchNow($this->invoice, $this->invoice->company, $this->invoice->client->primary_contact()->first());
    }

    public function testQuoteDesignExists()
    {

    	$modern = new Modern();

    	$designer = new Designer($modern, $this->company->settings->pdf_variables, 'quote');

    	$html = $designer->build($this->quote)->getHtml();

    	$this->assertNotNull($html);

    	//\Log::error($html);

    	$settings = $this->invoice->client->settings;
    	$settings->quote_design_id = "10";

    	$this->client->settings = $settings;
    	$this->client->save();

    	CreateQuotePdf::dispatchNow($this->quote, $this->quote->company, $this->quote->client->primary_contact()->first());
    }
    
}

            