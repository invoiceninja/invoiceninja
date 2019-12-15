<?php

namespace Feature;

use App\Mail\TemplateEmail;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Parsedown;
use Tests\MockAccountData;
use Tests\TestCase;

/**
* @test
*/
class InvoiceEmailTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use GeneratesCounter;

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

      //  \Log::error($this->invoice->makeValues());

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client);

        $message_array = $this->getEmailData($this->invoice);
        $message_array['title'] = &$message_array['subject'];
        $message_array['footer'] = 'The Footer';

        $template_style = $this->client->getSetting('email_style');

        $template_style = 'light';
        //iterate through the senders list and send from here

        $invitations = InvoiceInvitation::whereInvoiceId($this->invoice->id)->get();

        $invitations->each(function($invitation) use($message_array, $template_style) {

            $contact = ClientContact::find($invitation->client_contact_id)->first();

            if($contact->send_invoice && $contact->email)
            {
                //there may be template variables left over for the specific contact? need to reparse here
                
                //change the runtime config of the mail provider here:
                
                //send message
                Mail::to($contact->email)
                ->send(new TemplateEmail($message_array, $template_style, $this->user, $this->client));

                //fire any events
                

                sleep(5);
                
            }

        });
        


    }




















//TDD

    /**
     * Builds the correct template to send
     * @param  App\Models\Invoice $invoice The Invoice Model
     * @param  string $reminder_template The template name ie reminder1
     * @return array                   
     */
    private function getEmailData(Invoice $invoice, $reminder_template = null) :array
    {
        //client
        $client = $invoice->client;

        if(!$reminder_template)
            $reminder_template = $this->calculateTemplate($invoice);

        //Need to determine which email template we are producing
        $email_data = $this->generateTemplateData($invoice, $reminder_template);

        return $email_data;

    }

    private function generateTemplateData(Invoice $invoice, string $reminder_template) :array
    {
        $data = [];

        $client = $invoice->client;

        $body_template = $client->getSetting('email_template_'.$reminder_template);
        $subject_template = $client->getSetting('email_subject_'.$reminder_template);

        $data['body'] = $this->parseTemplate($invoice, $body_template, false);
        $data['subject'] = $this->parseTemplate($invoice, $subject_template, true);

        return $data;
    }

    private function parseTemplate(Invoice $invoice, string $template_data, bool $is_markdown = true) :string
    {
        $invoice_variables = $invoice->makeValues();

        //process variables
        $data = str_replace(array_keys($invoice_variables), array_values($invoice_variables), $template_data);

        //process markdown
        if($is_markdown)
            $data = Parsedown::instance()->line($data);

        return $data;
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