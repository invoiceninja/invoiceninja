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

namespace Tests\Feature\Email;

use App\Services\Email\EmailObject;
use App\Services\Email\EmailService;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Cache;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Services\Email\EmailService
 */
class EmailServiceTest extends TestCase
{
    use MakesHash;
    use GeneratesCounter;
    use MockAccountData;

    public EmailService $email_service;

    public EmailObject $email_object;

    protected function setUp() :void
    {
        parent::setUp();

        if (!class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
            $this->markTestSkipped('Skipped :: test not needed in this environment');
        }

        $this->makeTestData();

        $this->email_object = new EmailObject();
        $this->email_object->to = [new Address("testing@gmail.com", "Cool Name")];
        $this->email_object->attachments = [];
        $this->email_object->settings = $this->client->getMergedSettings();
        $this->email_object->company = $this->client->company;
        $this->email_object->client = $this->client;
        $this->email_object->email_template_subject = 'email_subject_statement';
        $this->email_object->email_template_body = 'email_template_statement';
        $this->email_object->variables = [
            '$client' => $this->client->present()->name(),
            '$start_date' => '2022-01-01',
            '$end_date' => '2023-01-01',
        ];

        $this->email_service = new EmailService($this->email_object, $this->company);
    }

    public function testScanEmailsAttemptedFromVerifiedAccounts()
    {
        $email_filter = new \Modules\Admin\Jobs\Account\EmailFilter($this->email_object, $this->client->company);

        Cache::put($this->account->key, 1);

        config(['ninja.environment' => 'hosted']);

        $this->account->account_sms_verified = true;
        $this->account->is_verified_account = false;
        $this->account->save();

        $this->assertFalse($this->email_service->preFlightChecksFail());

        collect($email_filter->getSpamKeywords())->each(function ($spam_subject) {
            $this->email_object->subject = $spam_subject;

            $this->assertTrue($this->email_service->preFlightChecksFail());
        });
    }



    public function scanEmailsAttemptedFromUnverifiedAccounts()
    {
        config(['ninja.environment' => 'hosted']);

        Cache::put($this->account->key, 1);

        $this->account->account_sms_verified = false;
        $this->account->save();

        $this->assertTrue($this->email_service->preFlightChecksFail());
    }


    public function testVerifiedAccountsSkipFilters()
    {
        config(['ninja.environment' => 'hosted']);

        Cache::put($this->account->key, 1);

        $this->account->is_verified_account = true;
        $this->account->save();

        $this->assertFalse($this->email_service->preFlightChecksFail());
    }

    public function testFlaggedInvalidEmailsPrevented()
    {
        config(['ninja.environment' => 'hosted']);

        Cache::put($this->account->key, 1);

        $this->email_object->to = [new Address("user@example.com", "Cool Name")];

        $this->assertTrue($this->email_service->preFlightChecksFail());


        collect([
            'user@example.com',
            '',
            'bademail',
            'domain.com',
        ])->each(function ($email) {
            $this->email_object->to = [new Address($email, "Cool Name")];

            $this->assertTrue($this->email_service->preFlightChecksFail());
        });
    }

    public function testFlaggedAccountsPrevented()
    {
        Cache::put($this->account->key, 1);

        config(['ninja.environment' => 'hosted']);

        $this->account->is_flagged = true;
        $this->account->save();

        $this->assertTrue($this->email_service->preFlightChecksFail());
    }

    public function testPreFlightChecksHosted()
    {
        Cache::put($this->account->key, 1);
    
        config(['ninja.environment' => 'hosted']);
    
        $this->assertFalse($this->email_service->preFlightChecksFail());
    }

    public function testPreFlightChecksSelfHost()
    {
        Cache::put($this->account->key, 1);

        config(['ninja.environment' => 'selfhost']);
    
        $this->assertFalse($this->email_service->preFlightChecksFail());
    }
}
