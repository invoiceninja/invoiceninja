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


        $this->invoice = factory(\App\Models\Invoice::class)->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
            ]);

        $this->invoice->uses_inclusive_taxes = false;

    	$settings = $this->invoice->client->settings;
    	$settings->invoice_design_id = "6";

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
    	$settings->quote_design_id = "6";

    	$this->client->settings = $settings;
    	$this->client->save();

    	CreateQuotePdf::dispatchNow($this->quote, $this->quote->company, $this->quote->client->primary_contact()->first());
    }
    
}

            