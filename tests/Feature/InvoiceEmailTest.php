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
        $email_data = $this->generateTemplateData($invoice, $reminder_template)

    }

    private function generateTemplateData(Invoice $invoice, string $reminder_template) :array
    {
        $data = [];

        $client = $invoice->client;

        $body_template = $client->getSetting('email_template_'.$reminder_template);
        $subject_template = $client->getSetting('email_subject_'.$reminder_template);

        $data['message'] = $this->parseTemplate($invoice, $body_template);
        $data['subject'] = $this->parseTemplate($invoice, $subject_template);

        return $data;
    }

    private function parseTemplate($invoice, $template_data) :string
    {

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
        else if($client->getSetting('enable_reminder1') !== false && $this->inReminderWindow($invoice, $client->getSetting('schedule_reminder1'), $client->getSetting('num_days_reminder1')))
        {
            return 'template1';
        }
        else if($client->getSetting('enable_reminder2') !== false && $this->inReminderWindow($invoice, $client->getSetting('schedule_reminder2'), $client->getSetting('num_days_reminder2')))
        {
            return 'template2';
        }
        else if($client->getSetting('enable_reminder3') !== false && $this->inReminderWindow($invoice, $client->getSetting('schedule_reminder3'), $client->getSetting('num_days_reminder3')))
        {
            return 'template3';
        }
        //also implement endless reminders here
        //
           
    }

    private function inReminderWindow($invoice, $schedule_reminder, $num_days_reminder)
    {
        switch ($schedule_reminder) {
            case 'after_invoice_date':
                return Carbon::parse($invoice->date)->addDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            case 'before_due_date':
                return Carbon::parse($invoice->due_date)->subDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            case 'after_due_date':
                return Carbon::parse($invoice->due_date)->addDays($num_days_reminder)->startOfDay()->eq(Carbon::now()->startOfDay());
                break;
            default:
                # code...
                break;
        }
    }
}