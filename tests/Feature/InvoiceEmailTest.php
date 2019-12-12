<?php

namespace Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
* @test
*/
class InvoiceEmailTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    public function test_initial_email_sends()
    {

    }




















//TDD

    /**
     * Builds the correct template to send
     * @param  App\Models\Invoice $invoice The Invoice Model
     * @param  string $reminder_template The template name ie reminder1
     * @return void                   
     */
    private function invoiceEmailWorkFlow($invoice, $reminder_template = null)
    {
        //client
        $client = $invoice->client;

        $template_style = $client->getSetting('email_style');

        if(!$reminder_template)
            $reminder_template = $this->calculateTemplate($invoice);

        //Need to determine which email template we are producing
        $message = $this->generateTemplateData($invoice)

        // $subject = 
        // $body =
    }

    private function calculateTemplate(Invoice $invoice) :string
    {
        //if invoice is currently a draft, or being marked as sent, this will be the initial email
        $client = $invoice->client;

        //if the invoice 
        if($invoice->status_id == Invoice::STATUS_DRAFT || Carbon::parse($invoice->due_date) > now()) 
        {
            return 'invoice';
        }
        else if($client->getSetting('enable_reminder1') !== false)
        {

        }
        
    }
}