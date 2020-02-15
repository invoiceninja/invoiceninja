<?php

namespace Feature;

use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Invoice\EmailInvoice;
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

    public function test_initial_email_send_emails()
    {

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client);

        $this->invoice->client_id = $this->client->id;
        $this->invoice->setRelation('client', $this->client);
        $this->invoice->save();

        $invitations = InvoiceInvitation::whereInvoiceId($this->invoice->id)->get();

        $email_builder = (new InvoiceEmail())->build($this->invoice, null, null);

        $invitations->each(function ($invitation) use ($email_builder) {

            if ($invitation->contact->send_email && $invitation->contact->email) {

                EmailInvoice::dispatch($email_builder, $invitation, $invitation->company);

                $this->expectsJobs(EmailInvoice::class);

            }
        });
        
    }

}
