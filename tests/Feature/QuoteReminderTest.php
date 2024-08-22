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

use Tests\TestCase;
use App\Models\User;
use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Quote;
use Tests\MockAccountData;
use App\Models\CompanyToken;
use App\Models\ClientContact;
use App\Jobs\Util\ReminderJob;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers App\Jobs\Util\QuoteReminderJob
 */
class QuoteReminderTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }
    public $company;

    public $user;

    public $payload;

    public $account;

    public $client;

    public $token;

    public $cu;

    public $invoice;

    private function buildData($settings = null)
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        if(!$settings) {
            $settings = CompanySettings::defaults();
            $settings->client_online_payment_notification = false;
            $settings->client_manual_payment_notification = false;
        }

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $this->cu->is_owner = true;
        $this->cu->is_admin = true;
        $this->cu->is_locked = false;
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;

        $company_token->save();

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'bob',
            'address1' => '1234',
            'balance' => 100,
            'paid_to_date' => 50,
        ]);

        ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'first_name' => 'john',
                'last_name' => 'doe',
                'email' => 'john@doe.com',
                'send_email' => true,
            ]);

        $this->quote = Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'date' => now()->addSeconds($this->client->timezone_offset())->format('Y-m-d'),
            'next_send_date' => null,
            'due_date' => Carbon::now()->addSeconds($this->client->timezone_offset())->addDays(5)->format('Y-m-d'),
            'last_sent_date' => now()->addSeconds($this->client->timezone_offset()),
            'reminder_last_sent' => null,
            'status_id' => 2,
            'amount' => 10,
            'balance' => 10,
        ]);

    }


    public function testNullReminder()
    {

        $settings = $this->company->settings;
        $settings->enable_quote_reminder1 = false;
        $settings->quote_schedule_reminder1 = '';
        $settings->quote_num_days_reminder1 = 1;

        $this->buildData(($settings));

        $this->quote->date = now()->subMonths(2)->format('Y-m-d');
        $this->quote->due_date = now()->subMonth()->format('Y-m-d');
        $this->quote->last_sent_date = now();
        $this->quote->next_send_date = null;

        $this->quote->service()->setReminder($settings)->save();

        $this->quote = $this->quote->fresh();

        $this->assertNull($this->quote->next_send_date);

    }

    public function testBeforeValidReminder()
    {

        $settings = $this->company->settings;
        $settings->enable_quote_reminder1 = true;
        $settings->quote_schedule_reminder1 = 'before_valid_until_date';
        $settings->quote_num_days_reminder1 = 1;

        $this->buildData(($settings));

        $this->quote->date = now()->addMonth()->format('Y-m-d');
        $this->quote->partial_due_date = null;
        $this->quote->due_date = now()->addMonths(2)->format('Y-m-d');
        $this->quote->last_sent_date = null;
        $this->quote->next_send_date = null;
        $this->quote->save();


        $this->assertTrue($this->quote->canRemind());

        $this->quote->service()->setReminder($settings)->save();

        $this->quote = $this->quote->fresh();

        $this->assertNotNull($this->quote->next_send_date);

        nlog($this->quote->next_send_date);
        $this->assertEquals(now()->addMonths(2)->subDay()->format('Y-m-d'), \Carbon\Carbon::parse($this->quote->next_send_date)->addSeconds($this->quote->client->timezone_offset())->format('Y-m-d'));

    }


}
