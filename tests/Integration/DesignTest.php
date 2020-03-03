<?php

namespace Tests\Integration;

use App\Designs\Designer;
use App\Designs\Modern;
use App\Jobs\Credit\CreateCreditPdf;
use App\Jobs\Invoice\CreateInvoicePdf;
use App\Jobs\Quote\CreateQuotePdf;
use App\Utils\Traits\GeneratesCounter;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Designs\Designer
 */
class DesignTest extends TestCase
{
  	use MockAccountData;
    use GeneratesCounter;

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
    	$settings->invoice_design_id = "5";

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

        $this->quote->client_id = $this->client->id;
        $this->quote->setRelation('client', $this->client);
        $this->quote->save();

    	$this->client->settings = $settings;
    	$this->client->save();

    	CreateQuotePdf::dispatchNow($this->quote, $this->quote->company, $this->quote->client->primary_contact()->first());
    }

    public function testCreditDesignExists()
    {

        $modern = new Modern();

        $designer = new Designer($modern, $this->company->settings->pdf_variables, 'credit');

        $html = $designer->build($this->credit)->getHtml();

        $this->assertNotNull($html);

        $settings = $this->invoice->client->settings;
        $settings->quote_design_id = "6";

        $this->credit->client_id = $this->client->id;
        $this->credit->setRelation('client', $this->client);
        $this->credit->save();
        
        $this->client->settings = $settings;
        $this->client->save();

        CreateCreditPdf::dispatchNow($this->credit, $this->credit->company, $this->credit->client->primary_contact()->first());
    }

    public function testAllDesigns()
    {

        for($x=1; $x<=10; $x++)
        {

        $settings = $this->invoice->client->settings;
        $settings->quote_design_id = (string)$x;
        
        $this->quote->client_id = $this->client->id;
        $this->quote->setRelation('client', $this->client);
        $this->quote->save();

        $this->client->settings = $settings;
        $this->client->save();

        CreateQuotePdf::dispatchNow($this->quote, $this->quote->company, $this->quote->client->primary_contact()->first());

        $this->quote->number = $this->getNextQuoteNumber($this->quote->client);
        $this->quote->save();

        }

        $this->assertTrue(true);

    }
    
}

            