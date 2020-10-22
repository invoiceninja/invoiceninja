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
namespace Tests\Integration;

use App\DataMapper\CompanySettings;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceItemFactory;
use App\Jobs\Invoice\MarkInvoicePaid;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\MockAccountData;
use Tests\TestCase;
use Illuminate\Validation\ValidationException;

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

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutExceptionHandling();


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

        $settings->company_logo = 'https://www.invoiceninja.com/wp-content/uploads/2019/01/InvoiceNinja-Logo-Round-300x300.png';
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

        $this->company->settings = $settings;
        $this->company->save();

        $this->account->default_company_id = $this->company->id;
        $this->account->save();

        $this->user = User::whereEmail('user@example.com')->first();

        if (! $this->user) {
            $this->user = User::factory()->create([
                'account_id' => $this->account->id,
                'password' => Hash::make('ALongAndBriliantPassword'),
                'confirmation_code' => $this->createDbHash(config('database.default')),
            ]);
        }

        $cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken;
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->save();

        $this->client = Client::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
            ]);

        ClientContact::factory()->create([
                'user_id' => $this->user->id,
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
        $line_items = [];

        $item = [];
        $item['quantity'] = 1;
        $item['cost'] = 10;

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

        $this->assertEquals($invoice->client->balance, 10);

        $invoice_ledger = $invoice->company_ledger->sortByDesc('id')->first();

        $this->assertEquals($invoice_ledger->balance, $invoice->client->balance);
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
        $this->assertEquals($invoice->client->balance, 20);
        $invoice_ledger = $invoice->company_ledger->sortByDesc('id')->first();

        $this->assertEquals($invoice_ledger->balance, $invoice->client->balance);
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
            info(print_r($e->validator->getMessageBag(), 1));
        }

        $acc = $response->json();

        $payment = Payment::find($this->decodePrimaryKey($acc['data']['id']));

        $payment_ledger = $payment->company_ledger->sortByDesc('id')->first();

        //info($payment->client->balance);

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
