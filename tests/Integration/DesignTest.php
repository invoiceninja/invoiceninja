<?php

namespace Tests\Integration;

use App\Designs\Bold;
use App\Designs\Business;
use App\Designs\Clean;
use App\Designs\Designer;
use App\Designs\Modern;
use App\Jobs\Credit\CreateCreditPdf;
use App\Jobs\Invoice\CreateInvoicePdf;
use App\Jobs\Quote\CreateQuotePdf;
use App\Models\ClientContact;
use App\Models\Design;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Designs\Designer
 */
class DesignTest extends TestCase
{
    use MakesInvoiceHtml;
    use PdfMaker;
    use MockAccountData;
    use GeneratesCounter;
    use MakesHash;


    /**
     * @var ClientContact
     */
    private $contact;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceDesignExists()
    {
        $this->contact = $this->invoice->client->primary_contact()->first();

        $design = json_decode(Design::find(3));

        $designer = new Designer($this->invoice, $design, $this->company->settings->pdf_variables, 'quote');

        $html = $designer->build()->getHtml();

        $this->assertNotNull($html);

        $this->invoice = factory(\App\Models\Invoice::class)->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
            ]);

        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->service()->createInvitations()->markSent()->save();
        $this->invoice->fresh();
        $this->invoice->load('invitations');

        $settings = $this->invoice->client->settings;
        $settings->invoice_design_id = "VolejRejNm";
        $settings->all_pages_header = true;
        $settings->all_pages_footer = true;

        $this->client->settings = $settings;
        $this->client->save();

        CreateInvoicePdf::dispatchNow($this->invoice->invitations->first());
    }

    public function testQuoteDesignExists()
    {
        $this->contact = $this->quote->client->primary_contact()->first();

        $design = json_decode(Design::find(3));

        $designer = new Designer($this->quote, $design, $this->company->settings->pdf_variables, 'quote');

        $html = $designer->build()->getHtml();

        $this->assertNotNull($html);

        $this->quote = factory(\App\Models\Invoice::class)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

        $this->quote->uses_inclusive_taxes = false;

        $this->quote->service()->createInvitations()->markSent()->save();

        $this->quote->fresh();
        $this->quote->load('invitations');
        $settings = $this->quote->client->settings;
        $settings->invoice_design_id = "VolejRejNm";
        $settings->all_pages_header = true;
        $settings->all_pages_footer = true;

        $this->client->settings = $settings;
        $this->client->save();

        $this->quote->setRelation('client', $this->client);

        $invitation = $this->quote->invitations->first();
        $invitation->setRelation('quote', $this->quote);

        CreateQuotePdf::dispatchNow($invitation);
    }


    public function testCreditDesignExists()
    {
        $design = json_decode(Design::find(3));

        $designer = new Designer($this->credit, $design, $this->company->settings->pdf_variables, 'credit');

        $html = $designer->build()->getHtml();

        $this->assertNotNull($html);

        $settings = $this->invoice->client->settings;
        $settings->quote_design_id = "4";
        $settings->all_pages_header = true;
        $settings->all_pages_footer = true;

        $this->credit->client_id = $this->client->id;
        $this->credit->setRelation('client', $this->client);

        $this->credit->service()->createInvitations()->markSent()->save();
        $this->credit->fresh();
        $this->credit->load('invitations');
                
        $invitation = $this->credit->invitations->first();
        $invitation->setRelation('credit', $this->credit);


        $this->client->settings = $settings;
        $this->client->save();

        CreateCreditPdf::dispatchNow($invitation);
    }

    public function testAllDesigns()
    {

            $this->quote->client_id = $this->client->id;
            $this->quote->setRelation('client', $this->client);
            $this->quote->save();


        for ($x=1; $x<=10; $x++) {

            $settings = $this->invoice->client->settings;
            $settings->quote_design_id = (string)$this->encodePrimaryKey($x);
            $settings->all_pages_header = true;
            $settings->all_pages_footer = true;
            $this->client->settings = $settings;
            $this->client->save();

            $this->quote->service()->createInvitations()->markSent()->save();
            $this->quote->fresh();
            $this->quote->load('invitations');

            $invitation = $this->quote->invitations->first();
            $invitation->setRelation('quote', $this->quote);

            CreateQuotePdf::dispatchNow($invitation);

            $this->quote->number = $this->getNextQuoteNumber($this->quote->client);

//            $this->quote->save();

        }

        $this->assertTrue(true);
    }

}
