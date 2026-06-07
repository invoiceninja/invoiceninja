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
use App\Models\Payment;
use App\Models\User;
use App\Services\Report\ClientSalesReport;
use App\Services\Template\TemplateService;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 *
 */
class ClientSalesReportTest extends TestCase
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

        $pl = new ClientSalesReport($this->company, $this->payload);

        $this->assertInstanceOf(ClientSalesReport::class, $pl);

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

        $pl = new ClientSalesReport($this->company, $this->payload);
        $response = $pl->run();

        $this->assertIsString($response);

        $this->account->delete();
    }

    /**
     * Exercises the GROUP BY aggregate path with multiple clients × multiple
     * statuses. Asserts the report runs end-to-end and that draft invoices
     * are excluded from the aggregate (status filter check).
     */
    public function testReportAggregatesAcrossClients()
    {
        $this->buildData();

        $payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $clients = collect();
        for ($i = 0; $i < 3; $i++) {
            $clients->push(Client::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'is_deleted' => 0,
                'name' => 'Test Client ' . $i,
                'balance' => ($i + 1) * 100,
            ]));
        }

        foreach ($clients as $idx => $client) {
            foreach ([Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID, Invoice::STATUS_DRAFT] as $status) {
                Invoice::factory()->create([
                    'client_id' => $client->id,
                    'user_id' => $this->user->id,
                    'company_id' => $this->company->id,
                    'amount' => 100 + $idx,
                    'balance' => 50 + $idx,
                    'total_taxes' => 10,
                    'status_id' => $status,
                    'date' => now()->format('Y-m-d'),
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
            }
        }

        $report = new ClientSalesReport($this->company, $payload);
        $output = $report->run();

        $this->assertIsString($output);
        // Each client created 4 invoices but only 3 (sent/partial/paid) are aggregated.
        // Spot-check by ensuring a "3" appears as the invoice-count column for at least one client.
        $this->assertStringContainsString(',3,', $output);
        // Draft status (count=1 in raw data) must not leak — assert no aggregate of 4 appears.
        $this->assertStringNotContainsString(',4,', $output);

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

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 100,
            'balance' => 25,
            'total_taxes' => 10,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2025-01-15',
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

        Invoice::factory()->create([
            'client_id' => $usd_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 75,
            'balance' => 15,
            'total_taxes' => 5,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2025-01-20',
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

        Payment::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 40,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'is_deleted' => false,
            'date' => '2025-01-25',
            'currency_id' => 2,
        ]);

        Payment::factory()->create([
            'client_id' => $usd_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 35,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'is_deleted' => false,
            'date' => '2025-01-26',
            'currency_id' => 1,
        ]);

        $report = new ClientSalesReport($this->company->fresh(), [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'date_range' => 'custom',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $response = $report->run();
        $gbp_summary_row = $this->findClientSummaryRow($response, $this->client);
        $usd_summary_row = $this->findClientSummaryRow($response, $usd_client);
        $gbp_invoice_row = $this->findPivotRow($response, $this->client, 'Invoices by month');
        $usd_invoice_row = $this->findPivotRow($response, $usd_client, 'Invoices by month');
        $gbp_payment_row = $this->findPivotRow($response, $this->client, 'Payments by month');
        $usd_payment_row = $this->findPivotRow($response, $usd_client, 'Payments by month');

        $this->assertStringContainsString('Currency,GBP', $response);
        $this->assertStringContainsString('Currency,USD', $response);
        $this->assertGreaterThanOrEqual(3, substr_count($response, 'Currency,GBP'));
        $this->assertGreaterThanOrEqual(3, substr_count($response, 'Currency,USD'));
        $this->assertStringContainsString('100.00 GBP', $gbp_summary_row[4]);
        $this->assertStringContainsString('75.00 USD', $usd_summary_row[4]);
        $this->assertStringContainsString('40.00 GBP', $gbp_summary_row[7]);
        $this->assertStringContainsString('35.00 USD', $usd_summary_row[7]);
        $this->assertStringContainsString('100.00 GBP', implode('|', $gbp_invoice_row));
        $this->assertStringContainsString('75.00 USD', implode('|', $usd_invoice_row));
        $this->assertStringContainsString('40.00 GBP', implode('|', $gbp_payment_row));
        $this->assertStringContainsString('35.00 USD', implode('|', $usd_payment_row));

        $reflection = new \ReflectionClass($report);
        $property = $reflection->getProperty('client_groups');
        $property->setAccessible(true);
        $groups = $property->getValue($report);

        $this->assertArrayHasKey('GBP', $groups);
        $this->assertArrayHasKey('USD', $groups);

        $this->account->delete();
    }

    public function testReportExcludesClientsWithZeroInvoices(): void
    {
        $this->buildData();

        $invoice_client = Client::factory()->create([
            'name' => 'Invoice Client',
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        $payment_only_client = Client::factory()->create([
            'name' => 'Payment Only Client',
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->create([
            'client_id' => $invoice_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 150,
            'balance' => 0,
            'total_taxes' => 0,
            'status_id' => Invoice::STATUS_PAID,
            'date' => '2025-04-10',
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

        Payment::factory()->create([
            'client_id' => $payment_only_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 90,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'is_deleted' => false,
            'date' => '2025-04-15',
        ]);

        $report = new ClientSalesReport($this->company, [
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-30',
            'date_range' => 'custom',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();

        $this->assertStringContainsString('Invoice Client', $out);
        $this->assertStringContainsString('150.00', $out);
        $this->assertStringNotContainsString('Payment Only Client', $out);
        $this->assertStringNotContainsString('90.00', $out);

        $this->account->delete();
    }

    public function testPdfTemplateKeepsMonthlyTablesWithinPrintableWidth(): void
    {
        $template = file_get_contents(resource_path('/views/templates/reports/client_sales_report.html'));
        $clientName = str_repeat('Long Client Name ', 8);
        $monthlyClientName = str_repeat('Monthly Client Name ', 8);
        $idNumber = str_repeat('ID-', 12);
        $truncatedClientName = substr($clientName, 0, 25);
        $truncatedMonthlyClientName = substr($monthlyClientName, 0, 25);
        $truncatedIdNumber = substr($idNumber, 0, 14);
        $monthlyHeader = [];

        for ($month = 1; $month <= 24; $month++) {
            $monthlyHeader[] = 'Month-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '-2025';
        }

        $invoiceHeader = array_slice($monthlyHeader, 2);
        $paymentHeader = array_slice($monthlyHeader, 5);

        $invoiceMonthlyRow = array_merge(
            [$monthlyClientName],
            array_fill(0, count($invoiceHeader), '123,456,789.99 GBP')
        );
        $paymentMonthlyRow = array_merge(
            [$monthlyClientName],
            array_fill(0, count($paymentHeader), '123,456,789.99 GBP')
        );

        $this->assertIsString($template);

        $html = (new TemplateService())
            ->setData([
                'client_groups' => [[
                    'currency' => 'GBP',
                    'clients' => [[
                        $clientName,
                        str_repeat('CLIENT-', 8),
                        $idNumber,
                        '12',
                        '123,456,789.99 GBP',
                        '987,654,321.99 GBP',
                        '456,789,123.99 GBP',
                        '321,987,654.99 GBP',
                    ]],
                ]],
                'monthly_header' => $monthlyHeader,
                'monthly_invoice_header' => $invoiceHeader,
                'monthly_payment_header' => $paymentHeader,
                'monthly_invoice_groups' => [[
                    'currency' => 'GBP',
                    'rows' => [$invoiceMonthlyRow],
                ]],
                'monthly_payment_groups' => [[
                    'currency' => 'GBP',
                    'rows' => [$paymentMonthlyRow],
                ]],
                'monthly_skipped' => false,
                'company_logo' => '',
                'created_on' => '2026-05-13',
                'created_by' => 'Invoice Ninja',
            ])
            ->setRawTemplate($template)
            ->parseNinjaBlocks()
            ->save()
            ->getHtml();

        $this->assertStringContainsString($truncatedClientName, $html);
        $this->assertStringContainsString($truncatedMonthlyClientName, $html);
        $this->assertStringContainsString($truncatedIdNumber, $html);
        $this->assertStringNotContainsString($clientName, $html);
        $this->assertStringNotContainsString($monthlyClientName, $html);
        $this->assertStringNotContainsString($idNumber, $html);
        $this->assertStringContainsString('table-layout: auto;', $html);
        $this->assertStringContainsString('class="monthly-table"', $html);
        $this->assertStringContainsString('class="monthly-client"', $html);
        $this->assertStringContainsString('class="monthly-period"', $html);
        $this->assertStringContainsString('font-size: 8px;', $html);
        $this->assertStringContainsString('font-size: min(2vw, 18px);', $html);
        $this->assertStringContainsString('padding-top: 4px;', $html);
        $this->assertStringContainsString('padding-bottom: 4px;', $html);
        $this->assertStringContainsString('padding-left: 1rem !important;', $html);
        $this->assertStringContainsString('padding-right: 1rem !important;', $html);
        $this->assertStringContainsString('white-space: nowrap;', $html);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $html);
        $this->assertStringContainsString('word-break: break-word;', $html);
        $this->assertStringContainsString('class="align-left"', $html);
        $this->assertStringNotContainsString('table-layout: fixed;', $html);
        $this->assertStringNotContainsString('padding: 0;', $html);
        $this->assertStringNotContainsString('Month-01-2025', $html);
        $this->assertStringContainsString('Month-03-2025', $html);
        $this->assertStringContainsString('Month-06-2025', $html);
        $this->assertStringContainsString('Month-24-2025', $html);
        $this->assertStringNotContainsString('overflow-x: auto;', $html);
        $this->assertSame(2, substr_count($html, 'class="monthly-table"'));
    }


    /**
     * Invoices are counted by invoice date; payments by payment date. Clients
     * are only included when the report window contains at least one invoice.
     */
    public function testInvoicedAndPaymentsUseTheirOwnDates()
    {
        $this->buildData();

        $invoice_date = '2025-01-15';
        $payment_date = '2025-03-20';

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 500,
            'balance' => 0,
            'total_taxes' => 50,
            'status_id' => Invoice::STATUS_PAID,
            'date' => $invoice_date,
            'created_at' => $invoice_date . ' 00:00:00',
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $payment = Payment::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 500,
            'refunded' => 0,
            'applied' => 500,
            'status_id' => Payment::STATUS_COMPLETED,
            'is_deleted' => false,
            'date' => $payment_date,
        ]);
        $payment->invoices()->attach($invoice->id, ['amount' => 500]);

        // Window covers invoice only.
        $invoice_window = new ClientSalesReport($this->company, [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);
        $out = $invoice_window->run();
        $this->assertStringContainsString('$500.00', $out); // invoiced amount
        $this->assertStringContainsString('$0.00', $out);   // amount_paid column = 0

        // Window covers payment only.
        $payment_window = new ClientSalesReport($this->company, [
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-31',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);
        $out = $payment_window->run();
        $this->assertStringNotContainsString($this->client->present()->name(), $out);
        $this->assertStringNotContainsString('500.00', $out);

        $this->account->delete();
    }

    /**
     * Refunded portion of a payment must be excluded from amount_paid.
     */
    public function testPaymentsSubtractRefunds()
    {
        $this->buildData();

        $payment_date = '2025-06-10';

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 300,
            'balance' => 0,
            'total_taxes' => 0,
            'status_id' => Invoice::STATUS_PAID,
            'date' => $payment_date,
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

        Payment::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 300,
            'refunded' => 100,
            'status_id' => Payment::STATUS_PARTIALLY_REFUNDED,
            'is_deleted' => false,
            'date' => $payment_date,
        ]);

        $report = new ClientSalesReport($this->company, [
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();
        $data_row = $this->findClientSummaryRow($out, $this->client);
        $this->assertStringContainsString('200.00', $data_row[7]);

        $this->account->delete();
    }

    /**
     * The invoice aggregate must filter on `invoices.date` (business date),
     * not `created_at`. An invoice drafted in December but dated and sent
     * in January must appear in a January report.
     */
    public function testInvoiceFilteredByBusinessDateNotCreatedAt()
    {
        $this->buildData();

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 750,
            'balance' => 750,
            'total_taxes' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2025-01-10',
            'created_at' => '2024-12-20 09:00:00',
            'updated_at' => '2025-01-10 09:00:00',
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $report = new ClientSalesReport($this->company, [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();
        $data_row = $this->findClientSummaryRow($out, $this->client);
        $this->assertSame('1', (string) $data_row[3]);
        $this->assertStringContainsString('750.00', $data_row[4]);

        $this->account->delete();
    }

    /**
     * Archived (soft-deleted) invoices and payments are still real business
     * records in Invoice Ninja's model — only `is_deleted = 1` means truly
     * deleted. The aggregates must include `deleted_at IS NOT NULL` rows and
     * only exclude `is_deleted = 1`.
     */
    public function testArchivedRecordsAreIncludedButTrulyDeletedAreNot()
    {
        $this->buildData();

        $invoice_date = '2025-02-05';
        $payment_date = '2025-02-10';

        $archived = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 400, 'balance' => 0, 'total_taxes' => 0,
            'status_id' => Invoice::STATUS_PAID,
            'date' => $invoice_date,
            'deleted_at' => now(),
            'is_deleted' => 0,
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 999, 'balance' => 999, 'total_taxes' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => $invoice_date,
            'deleted_at' => now(),
            'is_deleted' => 1,
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        Payment::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 400, 'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'is_deleted' => false,
            'deleted_at' => now(),
            'date' => $payment_date,
        ]);

        Payment::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 888, 'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'is_deleted' => true,
            'date' => $payment_date,
        ]);

        $report = new ClientSalesReport($this->company, [
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-28',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();
        $data_row = $this->findClientSummaryRow($out, $this->client);

        $this->assertSame('1', (string) $data_row[3]);
        $this->assertStringContainsString('400.00', $data_row[4]);
        $this->assertStringContainsString('400.00', $data_row[7]);
        $this->assertStringNotContainsString('999', $data_row[4]);
        $this->assertStringNotContainsString('888', $data_row[7]);

        $this->account->delete();
    }

    /**
     * Range > 24 months: the monthly pivot sections still emit, but the lower
     * bound is clipped to the most recent 24 months before leading empty
     * columns are removed. Data from before the clipped window must be excluded.
     */
    public function testMonthlySectionsClippedForLongRange()
    {
        $this->buildData();

        // End date is 2025-12-31 → clipped axis spans 2024-01 .. 2025-12.
        // Invoice dated 2020-05 must be excluded; 2025-06 must be included.
        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 999, 'balance' => 0, 'total_taxes' => 0,
            'status_id' => Invoice::STATUS_PAID,
            'date' => '2020-05-15',
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 250, 'balance' => 0, 'total_taxes' => 0,
            'status_id' => Invoice::STATUS_PAID,
            'date' => '2025-06-10',
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $report = new ClientSalesReport($this->company, [
            'start_date' => '2000-01-01',
            'end_date' => '2025-12-31',
            'date_range' => 'custom',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();

        // Sections present, skip-line absent.
        $this->assertStringContainsString('Invoices by month', $out);
        $this->assertStringContainsString('Payments by month', $out);
        $this->assertStringNotContainsString('Monthly breakdown skipped', $out);

        $header = $this->findPivotHeader($out, 'Invoices by month');

        $this->assertNotNull($header);
        $this->assertStringNotContainsString('January-2024', implode('|', $header));
        $this->assertStringContainsString('June-2025', implode('|', $header));
        $this->assertStringContainsString('December-2025', implode('|', $header));
        $this->assertStringNotContainsString('May-2020', $out);

        $row = $this->findPivotRow($out, $this->client, 'Invoices by month');
        $this->assertNotNull($row);
        $this->assertCount(8, $row); // [name, June-2025 through December-2025]
        $joined = implode('|', $row);
        $this->assertStringContainsString('250.00', $joined); // 2025-06 invoice present
        $this->assertStringNotContainsString('999', $joined); // 2020-05 invoice excluded

        $this->account->delete();
    }

    /**
     * date_range = "all" derives the axis end from the most recent invoice or
     * payment in the company and clips back 24 months. The skip-line marker
     * must NOT appear when data is present.
     */
    public function testMonthlySectionsClippedForAllRange()
    {
        $this->buildData();

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 175, 'balance' => 0, 'total_taxes' => 0,
            'status_id' => Invoice::STATUS_PAID,
            'date' => '2025-08-01',
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $report = new ClientSalesReport($this->company, [
            'date_range' => 'all',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();

        $this->assertStringNotContainsString('Monthly breakdown skipped', $out);
        $this->assertStringContainsString('Invoices by month', $out);
        // Most recent activity is August 2025 → end-of-axis column present.
        $this->assertStringContainsString('August-2025', $out);

        $row = $this->findPivotRow($out, $this->client, 'Invoices by month');
        $this->assertNotNull($row);
        $this->assertCount(2, $row);
        $this->assertStringContainsString('175.00', implode('|', $row));

        $this->account->delete();
    }

    /**
     * date_range = "all" with no invoice/payment data at all falls back to
     * the skip-line marker since there's no anchor to clip from.
     */
    public function testMonthlySectionsSkippedForAllRangeWithNoData()
    {
        $this->buildData();

        $report = new ClientSalesReport($this->company, [
            'date_range' => 'all',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();

        $this->assertStringContainsString('Monthly breakdown skipped', $out);

        $this->account->delete();
    }

    /**
     * Pivot tables: clients down rows, months across columns. Verifies leading
     * empty columns are omitted per section, trailing empty columns are kept,
     * cells reconcile to the underlying data, and empty-activity clients are
     * hidden from the matrix.
     */
    public function testMonthlyPivotEmitsExpectedShape()
    {
        $this->buildData();

        $active = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'Active Client',
            'balance' => 500,
        ]);

        $silent = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'Silent Client',
            'balance' => 100,
        ]);

        // Active client: invoice in Jan, invoice in Mar, payment in Feb.
        Invoice::factory()->create([
            'client_id' => $active->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 100, 'balance' => 0, 'total_taxes' => 0,
            'status_id' => Invoice::STATUS_PAID,
            'date' => '2025-01-15',
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        Invoice::factory()->create([
            'client_id' => $active->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 300, 'balance' => 0, 'total_taxes' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2025-03-20',
            'discount' => 0,
            'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0,
            'tax_name1' => '', 'tax_name2' => '', 'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        Payment::factory()->create([
            'client_id' => $active->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 250, 'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'is_deleted' => false,
            'date' => '2025-02-10',
        ]);

        $report = new ClientSalesReport($this->company, [
            'start_date' => '2025-01-01',
            'end_date' => '2025-03-31',
            'date_range' => 'custom',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();

        // Section titles present.
        $this->assertStringContainsString('Invoices by month', $out);
        $this->assertStringContainsString('Payments by month', $out);

        $invoice_header = $this->findPivotHeader($out, 'Invoices by month');
        $payment_header = $this->findPivotHeader($out, 'Payments by month');

        $this->assertSame(['Client Name', 'January-2025', 'February-2025', 'March-2025'], $invoice_header);
        $this->assertSame(['Client Name', 'February-2025', 'March-2025'], $payment_header);

        $invoice_row = $this->findPivotRow($out, $active, 'Invoices by month');
        $this->assertCount(4, $invoice_row); // [name, jan, feb, mar]
        $this->assertStringContainsString('100.00', $invoice_row[1]); // Jan
        $this->assertSame('', $invoice_row[2]);                       // Feb empty
        $this->assertStringContainsString('300.00', $invoice_row[3]); // Mar

        $payment_row = $this->findPivotRow($out, $active, 'Payments by month');
        $this->assertCount(3, $payment_row);                          // [name, feb, mar]
        $this->assertStringContainsString('250.00', $payment_row[1]); // Feb
        $this->assertSame('', $payment_row[2]);                       // Mar empty

        $this->assertNull($this->findPivotRow($out, $silent, 'Invoices by month'));
        $this->assertNull($this->findPivotRow($out, $silent, 'Payments by month'));

        $this->account->delete();
    }

    public function testMonthlySectionsSortClientsAlphabeticallyByName(): void
    {
        $this->buildData();

        $zebra = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'Zebra Monthly Client',
            'balance' => 900,
        ]);

        $alpha = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'Alpha Monthly Client',
            'balance' => 100,
        ]);

        foreach ([$zebra, $alpha] as $index => $client) {
            Invoice::factory()->create([
                'client_id' => $client->id,
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'amount' => $index === 0 ? 200 : 100,
                'balance' => 0,
                'total_taxes' => 0,
                'status_id' => Invoice::STATUS_PAID,
                'date' => '2025-01-15',
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

            Payment::factory()->create([
                'client_id' => $client->id,
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'amount' => $index === 0 ? 20 : 10,
                'refunded' => 0,
                'status_id' => Payment::STATUS_COMPLETED,
                'is_deleted' => false,
                'date' => '2025-01-20',
            ]);
        }

        $report = new ClientSalesReport($this->company, [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'date_range' => 'custom',
            'report_keys' => [],
            'user_id' => $this->user->id,
        ]);

        $out = $report->run();
        $expected = ['Alpha Monthly Client', 'Zebra Monthly Client'];

        $this->assertSame($expected, $this->findPivotClientNames($out, 'Invoices by month'));
        $this->assertSame($expected, $this->findPivotClientNames($out, 'Payments by month'));

        $this->account->delete();
    }

    /**
     * Locate a pivot row for $client within the named section.
     * Returns null if the row isn't present (e.g. client hidden as empty).
     */
    private function findPivotRow(string $output, Client $client, string $sectionTitle): ?array
    {
        $name = $client->present()->name();
        $lines = explode("\n", trim($output));
        $in_section = false;

        foreach ($lines as $line) {
            $cols = str_getcsv($line);

            if (($cols[0] ?? '') === $sectionTitle) {
                $in_section = true;
                continue;
            }

            // Next section title or per-client summary header ends the section.
            if ($in_section && in_array($cols[0] ?? '', ['Invoices by month', 'Payments by month'], true)) {
                break;
            }

            if ($in_section && ($cols[0] ?? '') === $name) {
                return $cols;
            }
        }

        return null;
    }

    private function findPivotClientNames(string $output, string $sectionTitle): array
    {
        $names = [];
        $lines = explode("\n", trim($output));
        $in_section = false;

        foreach ($lines as $line) {
            $cols = str_getcsv($line);
            $first_col = $cols[0] ?? '';

            if ($first_col === $sectionTitle) {
                $in_section = true;
                continue;
            }

            if ($in_section && in_array($first_col, ['Invoices by month', 'Payments by month'], true)) {
                break;
            }

            if (! $in_section || $first_col === '' || $first_col === 'Client Name' || $first_col === 'Currency') {
                continue;
            }

            $names[] = $first_col;
        }

        return $names;
    }

    private function findPivotHeader(string $output, string $sectionTitle): ?array
    {
        $lines = explode("\n", trim($output));
        $in_section = false;

        foreach ($lines as $line) {
            $cols = str_getcsv($line);

            if (($cols[0] ?? '') === $sectionTitle) {
                $in_section = true;
                continue;
            }

            if ($in_section && in_array($cols[0] ?? '', ['Invoices by month', 'Payments by month'], true)) {
                break;
            }

            if ($in_section && ($cols[0] ?? '') === 'Client Name') {
                return $cols;
            }
        }

        return null;
    }

    /**
     * Locate the per-client summary row for $client in the CSV output.
     * The summary section has 8 columns; the monthly pivot sections have a
     * different column count, so filtering by 8 columns + name is unambiguous
     * for single-client tests.
     */
    private function findClientSummaryRow(string $output, Client $client): array
    {
        $name = $client->present()->name();

        foreach (explode("\n", trim($output)) as $line) {
            $cols = str_getcsv($line);
            if (count($cols) === 8 && $cols[0] === $name) {
                return $cols;
            }
        }

        $this->fail("Per-client summary row for '{$name}' not found in CSV output.");
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
