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

namespace Tests\Feature\Export;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Credit;
use League\Csv\Reader;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\CompanyToken;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceItemFactory;
use App\Services\Report\ARDetailReport;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * @test
 */
class ReportCsvGenerationTest extends TestCase
{
    use MakesHash;

    public $faker;

    protected function setUp() :void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->buildData();


    }

    public $company;

    public $user;

    public $payload;

    public $account;

    public $client;

    public $token;

    public $cu;

    /**
     *      start_date - Y-m-d
            end_date - Y-m-d
            date_range -
                all
                last7
                last30
                this_month
                last_month
                this_quarter
                last_quarter
                this_year
                custom
            is_income_billed - true = Invoiced || false = Payments
            expense_billed - true = Expensed || false = Expenses marked as paid
            include_tax - true tax_included || false - tax_excluded
     */
    private function buildData()
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

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

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

        $company_token = new CompanyToken;
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;

        $company_token->save();

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'is_income_billed' => true,
            'include_tax' => false,
        ];

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'bob',
            'address1' => '1234'
        ]);

        ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'first_name' => 'john',
                'last_name' => 'doe',
                'email' => 'john@doe.com'
            ]);

    }

    public function testClientCsvGeneration()
    {

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/clients', $data);
       
        $csv = $response->streamedContent();

        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);
        
        $res = $reader->fetchColumnByName('Street');
        $res = iterator_to_array($res, true);

        $this->assertEquals('1234', $res[1]);

        $res = $reader->fetchColumnByName('Name');
        $res = iterator_to_array($res, true);

        $this->assertEquals('bob', $res[1]);

    }

    public function testClientContactCsvGeneration()
    {

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/contacts', $data);
       
        $csv = $response->streamedContent();

        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);
        
        $res = $reader->fetchColumnByName('First Name');
        $res = iterator_to_array($res, true);

        $this->assertEquals('john', $res[1]);

        $res = $reader->fetchColumnByName('Last Name');
        $res = iterator_to_array($res, true);

        $this->assertEquals('doe', $res[1]);

        $res = $reader->fetchColumnByName('Email');
        $res = iterator_to_array($res, true);

        $this->assertEquals('john@doe.com', $res[1]);

    }

    public function testCreditCsvGeneration()
    {

        Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'balance' => 50,
            'status_id' => 2,
            'discount' => 10,
            'po_number' => '1234',
            'public_notes' => 'Public',
            'private_notes' => 'Private',
            'terms' => 'Terms',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/credits', $data);

        $response->assertStatus(200);
       
    }
}