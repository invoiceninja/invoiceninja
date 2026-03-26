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

use App\DataMapper\CompanySettings;
use App\Export\CSV\ProductSalesExport;
use App\Factory\InvoiceItemFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 *
 *  App\Services\Report\ProductSalesExport
 */
class ProductSalesReportTest extends TestCase
{
    use MakesHash;
    use AppSetup;

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
            'email' => \Illuminate\Support\Str::random(32)."@example.com",
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

    public function testProductSalesInstance()
    {
        $this->buildData();

        $pl = new ProductSalesExport($this->company, $this->payload);

        $this->assertInstanceOf(ProductSalesExport::class, $pl);

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
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i = $i->calc()->getInvoice();

        $pl = new ProductSalesExport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);

        $this->account->delete();
    }


    public function testExclusiveTaxReport()
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

        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 100;
        $item->product_key = 'tax_test';
        $item->notes = 'exclusive tax product';
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'line_items' => [$item],
        ]);

        $i = $i->calc()->getInvoice();
        $i->save();

        $pl = new ProductSalesExport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);
        $this->assertStringContainsString('tax_test', $response);
        // 2 * 100 = 200 line_total, 10% tax = 20
        $this->assertStringContainsString('20', $response);

        $this->account->delete();
    }

    public function testInclusiveTaxReport()
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

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 110;
        $item->product_key = 'inclusive_test';
        $item->notes = 'inclusive tax product';
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 10;

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'uses_inclusive_taxes' => true,
            'line_items' => [$item],
        ]);

        $i = $i->calc()->getInvoice();
        $i->save();

        $pl = new ProductSalesExport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);
        $this->assertStringContainsString('inclusive_test', $response);
        // 110 inclusive of 10% VAT: tax = 110 - 110/1.1 = 10
        $this->assertStringContainsString('10', $response);

        $this->account->delete();
    }

    public function testAmountDiscountWithTaxReport()
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

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 200;
        $item->product_key = 'discount_tax_test';
        $item->notes = 'amount discount with tax';
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $item->discount = 0;

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'discount' => 50,
            'is_amount_discount' => true,
            'uses_inclusive_taxes' => false,
            'line_items' => [$item],
        ]);

        $i = $i->calc()->getInvoice();
        $i->save();

        $line_item = $i->line_items[0];
        // tax_amount is set by the calc engine, accounting for the amount discount correctly
        $this->assertGreaterThan(0, $line_item->tax_amount);

        $pl = new ProductSalesExport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);
        $this->assertStringContainsString('discount_tax_test', $response);
        // tax = (200 - 50) * 10% = 15, the calc engine handles this correctly
        $this->assertStringContainsString('15', $response);

        $this->account->delete();
    }

    public function testMultipleTaxRatesReport()
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

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->product_key = 'multi_tax_test';
        $item->notes = 'multiple tax rates';
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $item->tax_name2 = 'PST';
        $item->tax_rate2 = 5;

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'line_items' => [$item],
        ]);

        $i = $i->calc()->getInvoice();
        $i->save();

        $line_item = $i->line_items[0];
        // 100 * 10% = 10, 100 * 5% = 5, total = 15
        $this->assertEquals(15, $line_item->tax_amount);

        $pl = new ProductSalesExport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);
        $this->assertStringContainsString('multi_tax_test', $response);
        // Verify the CSV contains the tax total of 15
        $this->assertStringContainsString('15', $response);

        $this->account->delete();
    }

    public function testTaxTotalInSummaryAggregation()
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

        $item1 = InvoiceItemFactory::create();
        $item1->quantity = 1;
        $item1->cost = 100;
        $item1->product_key = 'agg_test';
        $item1->notes = 'aggregation test 1';
        $item1->tax_name1 = 'GST';
        $item1->tax_rate1 = 10;

        $item2 = InvoiceItemFactory::create();
        $item2->quantity = 1;
        $item2->cost = 200;
        $item2->product_key = 'agg_test';
        $item2->notes = 'aggregation test 2';
        $item2->tax_name1 = 'GST';
        $item2->tax_rate1 = 10;

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'line_items' => [$item1, $item2],
        ]);

        $i = $i->calc()->getInvoice();
        $i->save();

        $pl = new ProductSalesExport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);
        // 100 * 10% = 10, 200 * 10% = 20, total tax = 30
        // The summary section should aggregate both line items for 'agg_test'
        $this->assertStringContainsString('agg_test', $response);
        $this->assertStringContainsString('30', $response);

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

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->product_key = 'pumpkin';
        $item->notes = 'test_pumpkin';

        $line_items[] = $item;

        return $line_items;
    }
}
