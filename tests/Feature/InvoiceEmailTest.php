<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Jobs\Entity\EmailEntity;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    protected function setUp() :void
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
                EmailEntity::dispatch($invitation, $invitation->company);

                $this->expectsJobs(EmailEntity::class);
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
                EmailEntity::dispatch($invitation, $invitation->company);

                $this->expectsJobs(EmailEntity::class);
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
                EmailEntity::dispatch($invitation, $invitation->company);

                $this->expectsJobs(EmailEntity::class);
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
                EmailEntity::dispatch($invitation, $invitation->company);

                $this->expectsJobs(EmailEntity::class);
            }
        });

        $this->assertTrue(true);
    }
}
