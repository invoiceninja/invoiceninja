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

namespace Tests\Integration;

use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/** @test*/
class CompanyLedgerTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;

    public $company;

    public $client;

    public $user;

    public $token;

    public $account;

    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutExceptionHandling();

        $this->artisan('db:seed --force');

        /* Warm up the cache !*/
        $cached_tables = config('ninja.cached_tables');

        foreach ($cached_tables as $name => $class) {

            // check that the table exists in case the migration is pending
            if (! Schema::hasTable((new $class())->getTable())) {
                continue;
            }
            if ($name == 'payment_terms') {
                $orderBy = 'num_days';
            } elseif ($name == 'fonts') {
                $orderBy = 'sort_order';
            } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks'])) {
                $orderBy = 'name';
            } else {
                $orderBy = 'id';
            }
            $tableData = $class::orderBy($orderBy)->get();
            if ($tableData->count()) {
                Cache::forever($name, $tableData);
            }
        }

        $this->account = Account::factory()->create();
        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $settings = CompanySettings::defaults();

        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = 'user@example.com';
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->timezone_id = '1';
        $settings->language_id = '1';

        $this->company->settings = $settings;
        $this->company->save();

        $this->account->default_company_id = $this->company->id;
        $this->account->save();

        $user = User::whereEmail('user@example.com')->first();

        if (! $user) {
            $user = User::factory()->create([
                'account_id' => $this->account->id,
                'password' => Hash::make('ALongAndBriliantPassword'),
                'confirmation_code' => $this->createDbHash(config('database.default')),
            ]);
        }

        $cu = CompanyUserFactory::create($user->id, $this->company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken;
        $company_token->user_id = $user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;
        $company_token->save();

        $this->client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);
    }

    public function testBaseLine()
    {
        $this->assertEquals($this->company->invoices->count(), 0);
        $this->assertEquals($this->company->clients->count(), 1);
        $this->assertEquals($this->client->balance, 0);
    }

    public function testLedger()
    {
        $this->markTestSkipped();

        $line_items = [];

        $item = [];
        $item['quantity'] = 1;
        $item['cost'] = 10;
        $item['type_id'] = '1';

        $line_items[] = $item;

        $data = [
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'line_items' => $line_items,
        ];

        /* Test adding one invoice */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $data)
        ->assertStatus(200);

        $acc = $response->json();

        $invoice = Invoice::find($this->decodePrimaryKey($acc['data']['id']));

        //client->balance should = 10
        $invoice->service()->markSent()->save();

        $this->client = Client::find($this->client->id);
        $this->assertEquals($this->client->balance, 10);

        $invoice_ledger = $invoice->company_ledger->sortByDesc('id')->first();

        $this->assertEquals($invoice_ledger->balance, $this->client->balance);
        $this->assertEquals($invoice->client->paid_to_date, 0);

        /* Test adding another invoice */
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $data)
        ->assertStatus(200);

        $acc = $response->json();

        $invoice = Invoice::find($this->decodePrimaryKey($acc['data']['id']));
        $invoice->service()->markSent()->save();

        //client balance should = 20
        $this->assertEquals($this->client->fresh()->balance, 20);
        $invoice_ledger = $invoice->company_ledger->sortByDesc('id')->first();

        $this->assertEquals($invoice_ledger->balance, $this->client->fresh()->balance);
        $this->assertEquals($invoice->client->paid_to_date, 0);

        /* Test making a payment */

        $data = [
            'client_id' => $this->encodePrimaryKey($invoice->client_id),
            'amount' => $invoice->balance,
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($invoice->id),
                    'amount' => $invoice->balance,
                ],
            ],
            'date' => '2020/12/11',
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments/', $data);
        } catch (ValidationException $e) {
            nlog(print_r($e->validator->getMessageBag(), 1));
        }

        $acc = $response->json();

        $payment = Payment::find($this->decodePrimaryKey($acc['data']['id']));

        $payment_ledger = $payment->company_ledger->sortByDesc('id')->first();

        //nlog($payment->client->balance);

        $this->assertEquals($payment->client->balance, $payment_ledger->balance);
        $this->assertEquals($payment->client->paid_to_date, 10);

        $invoice = Invoice::find($invoice->id);

        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        /* Test making a refund of a payment */
        $refund = $invoice->amount;

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'client_id' => $this->encodePrimaryKey($invoice->client_id),
            'amount' => $refund,
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($invoice->id),
                    'amount' => $refund,
                ],
            ],
            'date' => '2020/12/11',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/refund', $data);

        $acc = $response->json();
        $invoice = Invoice::find($invoice->id);

        $this->assertEquals($refund, $invoice->balance);
    }
}
