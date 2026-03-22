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
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use App\Models\TransactionEvent;
use App\DataMapper\CompanySettings;
use App\Factory\InvoiceItemFactory;
use App\Services\Report\TaxSummaryReport;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Listeners\Invoice\InvoiceTransactionEventEntry;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;

/**
 *
 */
class TaxSummaryReportTest extends TestCase
{
    use MakesHash;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();
    }

    public $company;

    public $user;

    public $payload;

    public $account;

    public $client;

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
            'email' => \Illuminate\Support\Str::random(32).'@example.com',
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

        $this->user->companies()->attach($this->company->id, [
            'account_id' => $this->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => \App\DataMapper\CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        $company_token = new \App\Models\CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = true;

        $company_token->save();

        $truth = app()->make(\App\Utils\TruthSource::class);
        $truth->setCompanyUser($this->user->company_users()->first());
        $truth->setCompanyToken($company_token);
        $truth->setUser($this->user);
        $truth->setCompany($this->company);


        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'is_income_billed' => true,
            'include_tax' => false,
            'user_id' => $this->user->id,
        ];

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);
    }

    public function testUserSalesInstance()
    {
        $this->buildData();

        $pl = new TaxSummaryReport($this->company, $this->payload);

        $this->assertInstanceOf(TaxSummaryReport::class, $pl);

        $this->account->delete();
    }

    public function testCashTaxReport()
    {
        $this->buildData();


        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 17.5,
            'tax_rate3' => 5,
            'tax_name1' => 'GST',
            'tax_name2' => 'VAT',
            'tax_name3' => 'CA Sales Tax',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i = $i->calc()->getInvoice();

        (new InvoiceTransactionEventEntry())->run($i);

        $i2 = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 17.5,
            'tax_rate3' => 5,
            'tax_name1' => 'GST',
            'tax_name2' => 'VAT',
            'tax_name3' => 'CA Sales Tax',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i2 = $i2->calc()->getInvoice();
        $i2->service()->markPaid();

        for ($x = 0; $x < 50; $x++) {

            $date = now();

            $i3 = Invoice::factory()->create([
                'client_id' => $this->client->id,
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'amount' => 0,
                'balance' => 0,
                'status_id' => 1,
                'total_taxes' => 1,
                'date' => $date->addHours(12)->format('Y-m-d'),
                'terms' => 'nada',
                'discount' => 0,
                'tax_rate1' => 10,
                'tax_rate2' => 17.5,
                'tax_rate3' => 5,
                'tax_name1' => 'GST',
                'tax_name2' => 'VAT',
                'tax_name3' => 'CA Sales Tax',
                'uses_inclusive_taxes' => false,
                'line_items' => $this->buildLineItems(),
            ]);

            $i3 = $i3->calc()->getInvoice();

            $i3 = $i3->service()->markSent()->save();

            (new InvoiceTransactionEventEntry())->run($i3);

            $i3 = $i3->service()->markPaid()->save();

            $this->assertEquals($i3->amount, $i3->paid_to_date);

            (new InvoiceTransactionEventEntryCash())->run($i3, now()->subDays(30)->format('Y-m-d'), now()->addDays(30)->format('Y-m-d'));

        }

        (new InvoiceTransactionEventEntry())->run($i);



        $i2 = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 17.5,
            'tax_rate3' => 5,
            'tax_name1' => 'GST',
            'tax_name2' => 'VAT',
            'tax_name3' => 'CA Sales Tax',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i2 = $i2->calc()->getInvoice();
        $i2->service()->applyPaymentAmount(10, 'yadda')->save();

        $this->travelTo(now()->addDay());

        $i2->service()->applyPaymentAmount(1, 'yadda - 1')->save();

        $this->travelTo(now()->addDay());

        $i2->service()->applyPaymentAmount(2, 'yadda - 2')->save();

        $i2 = $i2->fresh();

        (new InvoiceTransactionEventEntryCash())->run($i2, now()->subDays(30)->format('Y-m-d'), now()->addDays(30)->format('Y-m-d'));

        $payment = $i2->payments()->first();

        // nlog(config('queue.default'));
        config(['queue.default' => 'sync']);

        $this->assertNotNull($payment);

        $data = [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'invoices' => [
                [
                    'invoice_id' => $i2->id,
                    'amount' => $payment->amount
                ]
            ],
            'date' => now()->format('Y-m-d'),
            'gateway_refund' => false,
            'email_receipt' => false,
            'via_webhook' => true,
        ];

        $payment->refund($data);

        // $pl = new \App\Services\Report\XLS\TaxReport($this->company, '2025-01-01', '2025-12-31');

        // $response = $pl->run()->getXlsFile();

        // $this->assertIsString($response);

        // try{
        //     file_put_contents('/home/david/ttx.xlsx', $response);
        // }
        // catch(\Throwable $e){
        //     nlog($e->getMessage());
        // }

        config(['queue.default' => 'redis']);

        $this->account->delete();

    }


    public function testSimpleReport()
    {
        $this->buildData();


        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 17.5,
            'tax_rate3' => 5,
            'tax_name1' => 'GST',
            'tax_name2' => 'VAT',
            'tax_name3' => 'CA Sales Tax',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i = $i->calc()->getInvoice();

        (new InvoiceTransactionEventEntry())->run($i);

        $i2 = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 17.5,
            'tax_rate3' => 5,
            'tax_name1' => 'GST',
            'tax_name2' => 'VAT',
            'tax_name3' => 'CA Sales Tax',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i2 = $i2->calc()->getInvoice();
        $i2->service()->markPaid();

        (new InvoiceTransactionEventEntryCash())->run($i2, now()->subDays(3000)->format('Y-m-d'), now()->addDays(3000)->format('Y-m-d'));

        $pl = new TaxSummaryReport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);

        $this->account->delete();
    }

    public function testSimpleReportXLS()
    {
        $this->buildData();


        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 220,
            'balance' => 0,
            'status_id' => 1,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 17.5,
            'tax_rate3' => 5,
            'tax_name1' => 'GST',
            'tax_name2' => 'VAT',
            'tax_name3' => 'CA Sales Tax',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i = $i->calc()->getInvoice();
        $i->service()->markSent()->save();

        (new InvoiceTransactionEventEntry())->run($i);

        $i2 = Invoice::factory()->create([
                    'client_id' => $this->client->id,
                    'user_id' => $this->user->id,
                    'company_id' => $this->company->id,
                    'amount' => 550,
                    'balance' => 0,
                    'status_id' => 2,
                    'total_taxes' => 1,
                    'date' => now()->format('Y-m-d'),
                    'terms' => 'nada',
                    'discount' => 0,
                    'tax_rate1' => 10,
                    'tax_rate2' => 17.5,
                    'tax_rate3' => 5,
                    'tax_name1' => 'GST',
                    'tax_name2' => 'VAT',
                    'tax_name3' => 'CA Sales Tax',
                    'uses_inclusive_taxes' => false,
                    'line_items' => $this->buildLineItems(),
                ]);

        $i2 = $i2->calc()->getInvoice();
        $i2->service()->markPaid()->save();

        (new InvoiceTransactionEventEntryCash())->run($i2, now()->subDays(30)->format('Y-m-d'), now()->addDays(30)->format('Y-m-d'));

        // $tr = new \App\Services\Report\XLS\TaxReport($this->company, '2025-01-01', '2025-12-31');
        // $response = $tr->run()->getXlsFile();

        // $this->assertNotEmpty($response);

        $this->assertNotNull(TransactionEvent::where('invoice_id', $i->id)->first());
        $this->assertNotNull(TransactionEvent::where('invoice_id', $i2->id)->first());


        $this->account->delete();
    }

    private function buildLineItems()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->product_key = 'test';
        $item->notes = 'test_product';
        // $item->task_id = $this->encodePrimaryKey($this->task->id);
        // $item->expense_id = $this->encodePrimaryKey($this->expense->id);

        $line_items[] = $item;


        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->product_key = 'pumpkin';
        $item->notes = 'test_pumpkin';
        // $item->task_id = $this->encodePrimaryKey($this->task->id);
        // $item->expense_id = $this->encodePrimaryKey($this->expense->id);

        $line_items[] = $item;


        return $line_items;
    }
}
