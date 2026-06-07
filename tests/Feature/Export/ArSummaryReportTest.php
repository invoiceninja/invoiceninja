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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\InvoiceItemFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Report\ARSummaryReport;
use App\Services\Template\TemplateService;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 *
 */
class ArSummaryReportTest extends TestCase
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

    public function testUserSalesInstance()
    {
        $this->buildData();

        $pl = new ARSummaryReport($this->company, $this->payload);

        $this->assertInstanceOf(ARSummaryReport::class, $pl);

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

        $pl = new ARSummaryReport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);

        $this->account->delete();
    }

    public function testReportGroupsMultipleClientCurrencies()
    {
        $this->buildData();

        $company_settings = $this->company->settings;
        $company_settings->currency_id = '1';
        $company_settings->show_currency_code = true;
        $this->company->settings = $company_settings;
        $this->company->save();

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '2';
        $client_settings->show_currency_code = true;
        $this->client->settings = $client_settings;
        $this->client->save();
        $this->client->refresh();

        $usd_client_settings = ClientSettings::defaults();
        $usd_client_settings->currency_id = '1';
        $usd_client_settings->show_currency_code = true;

        $usd_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $usd_client_settings,
            'is_deleted' => 0,
        ]);

        $zero_client_settings = ClientSettings::defaults();
        $zero_client_settings->currency_id = '3';
        $zero_client_settings->show_currency_code = true;

        Client::factory()->create([
            'name' => 'Zero Balance EUR Client',
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $zero_client_settings,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 100,
            'balance' => 100,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->subDays(10)->format('Y-m-d'),
            'is_deleted' => false,
        ]);

        Invoice::factory()->create([
            'client_id' => $usd_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 75,
            'balance' => 75,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->subDays(10)->format('Y-m-d'),
            'is_deleted' => false,
        ]);

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $report = new ARSummaryReport($this->company->fresh(), $this->payload);
        $response = $report->run();

        $this->assertStringContainsString('Currency,GBP', $response);
        $this->assertStringContainsString('Currency,USD', $response);
        $this->assertStringNotContainsString('Currency,EUR', $response);
        $this->assertStringNotContainsString('Zero Balance EUR Client', $response);
        $this->assertStringContainsString('100.00 GBP', $response);
        $this->assertStringContainsString('75.00 USD', $response);

        $reflection = new \ReflectionClass($report);
        $property = $reflection->getProperty('client_groups');
        $property->setAccessible(true);
        $this->assertArrayNotHasKey('EUR', $property->getValue($report));

        $this->account->delete();
    }

    public function testSummaryReportSortsByClientName(): void
    {
        $report = new ARSummaryReport(new Company(), ['report_keys' => []]);
        $query = Client::query()->orderBy('balance', 'DESC');
        $method = new \ReflectionMethod($report, 'sortClientsByName');
        $method->setAccessible(true);

        $sortedQuery = $method->invoke($report, $query);
        $sql = strtolower($sortedQuery->toSql());

        $this->assertMatchesRegularExpression('/order by [`"]name[`"] asc, [`"]id[`"] asc/', $sql);
        $this->assertStringNotContainsString('balance', $sql);
    }

    public function testPdfTemplateKeepsSummaryTableWithinPrintableWidth(): void
    {
        $template = file_get_contents(resource_path('/views/templates/reports/ar_summary_report.html'));
        $clientName = str_repeat('Long Client Name ', 8);
        $idNumber = str_repeat('ID-', 12);
        $truncatedClientName = substr($clientName, 0, 25);
        $truncatedIdNumber = substr($idNumber, 0, 14);

        $this->assertIsString($template);

        $html = (new TemplateService())
            ->setData([
                'client_groups' => [[
                    'currency' => 'USD',
                    'clients' => [[
                        $clientName,
                        str_repeat('CLIENT-', 8),
                        $idNumber,
                        '$1,000.00 USD',
                        '$2,000.00 USD',
                        '$3,000.00 USD',
                        '$4,000.00 USD',
                        '$5,000.00 USD',
                        '$6,000.00 USD',
                        '$21,000.00 USD',
                    ]],
                ]],
                'company_logo' => '',
                'created_on' => '2026-05-13',
                'created_by' => 'Invoice Ninja',
            ])
            ->setRawTemplate($template)
            ->parseNinjaBlocks()
            ->save()
            ->getHtml();

        $this->assertStringContainsString($truncatedClientName, $html);
        $this->assertStringContainsString($truncatedIdNumber, $html);
        $this->assertStringNotContainsString($clientName, $html);
        $this->assertStringNotContainsString($idNumber, $html);
        $this->assertStringContainsString('table-layout: auto;', $html);
        $this->assertStringContainsString('padding-top: 4px;', $html);
        $this->assertStringContainsString('padding-bottom: 4px;', $html);
        $this->assertStringContainsString('white-space: nowrap;', $html);
        $this->assertStringContainsString('font-size: min(2vw, 18px);', $html);
        $this->assertStringContainsString('class="align-left"', $html);
        $this->assertStringNotContainsString('table-layout: fixed;', $html);
        $this->assertStringNotContainsString('overflow-x: auto;', $html);
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
