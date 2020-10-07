<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
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
 * @covers App\Jobs\Invoice\EmailInvoice
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
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;

        $client_settings = $this->client->settings;
        $client_settings->email_style = 'dark';
        $this->client->settings = $client_settings;
        $this->client->save();

        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                $email_builder = (new InvoiceEmail())->build($invitation, null);

                EmailInvoice::dispatchNow($email_builder, $invitation, $invitation->company);

                $this->expectsJobs(EmailInvoice::class);
            }
        });

        $this->assertTrue(true);
    }

    public function testTemplateThemes()
    {
        $settings = $this->company->settings;
        $settings->email_style = 'light';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;
        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                $email_builder = (new InvoiceEmail())->build($invitation, null);

                EmailInvoice::dispatchNow($email_builder, $invitation, $invitation->company);

                $this->expectsJobs(EmailInvoice::class);
            }
        });

        $settings = $this->company->settings;
        $settings->email_style = 'dark';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;

        $client_settings = $this->client->settings;
        $client_settings->email_style = 'dark';
        $this->client->settings = $client_settings;
        $this->client->save();

        $this->invoice->setRelation('client', $this->client);
        $this->invoice->save();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                $email_builder = (new InvoiceEmail())->build($invitation, null);

                EmailInvoice::dispatchNow($email_builder, $invitation, $invitation->company);

                $this->expectsJobs(EmailInvoice::class);
            }
        });

        $settings = $this->company->settings;
        $settings->email_style = 'plain';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;
        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                $email_builder = (new InvoiceEmail())->build($invitation, null);

                EmailInvoice::dispatchNow($email_builder, $invitation, $invitation->company);

                $this->expectsJobs(EmailInvoice::class);
            }
        });

        $this->assertTrue(true);
    }
}
