<?php

namespace Feature;

use App\Mail\TemplateEmail;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\InvoiceEmailBuilder;
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

        $this->invoice->client = $this->client;

        $message_array = $this->invoice->getEmailData();
        $message_array['title'] = &$message_array['subject'];
        $message_array['footer'] = 'The Footer';

 //       $template_style = $this->client->getSetting('email_style');

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













}