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
use App\Factory\InvoiceItemFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Report\ClientSalesReport;
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


    /**
     * Invoices are counted by invoice date; payments by payment date. A report
     * scoped to a window that contains ONLY the payment date must show zero
     * invoiced and the full payment amount; a window that contains ONLY the
     * invoice date must show the invoice amount and zero payments.
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
        $data_row = $this->findClientSummaryRow($out, $this->client);
        // report_keys: [client_name, client_number, id_number, invoices, amount, balance, total_taxes, amount_paid]
        $this->assertSame('0', (string) $data_row[3]);          // invoice count
        $this->assertStringContainsString('0.00', $data_row[4]); // invoiced amount = 0
        $this->assertStringContainsString('500.00', $data_row[7]); // amount_paid = 500

        $this->account->delete();
    }

    /**
     * Refunded portion of a payment must be excluded from amount_paid.
     */
    public function testPaymentsSubtractRefunds()
    {
        $this->buildData();

        $payment_date = '2025-06-10';

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
     * bound is clipped so the axis covers the most recent 24 months ending at
     * the user's selected end_date. Data from before the clipped window must
     * be excluded.
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

        // Earliest column is January-2024 (clip lower bound), latest is December-2025.
        $this->assertStringContainsString('January-2024', $out);
        $this->assertStringContainsString('December-2025', $out);
        // Pre-clip month must NOT appear as a column header.
        $this->assertStringNotContainsString('May-2020', $out);

        // The pivot row contains the clipped invoice and 24 columns.
        $row = $this->findPivotRow($out, $this->client, 'Invoices by month');
        $this->assertNotNull($row);
        $this->assertCount(25, $row); // [name, ...24 months]
        $joined = implode('|', $row);
        $this->assertStringContainsString('250.00', $joined); // 2025-06 invoice present
        $this->assertStringNotContainsString('999', $joined); // 2020-05 invoice excluded

        $this->account->delete();
    }

    /**
     * date_range = "all" also suppresses the monthly pivot.
     */
    public function testMonthlySectionsSkippedForAllRange()
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
     * Pivot tables: clients down rows, months across columns. Verifies the
     * column header includes every month in the range (including ones with
     * zero activity), that cells reconcile to the underlying data, and that
     * empty-activity clients are hidden from the matrix.
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

        // Header includes all three months in the range — Feb has zero invoices
        // for Active Client but must still appear as a column.
        $this->assertStringContainsString('January-2025', $out);
        $this->assertStringContainsString('February-2025', $out);
        $this->assertStringContainsString('March-2025', $out);

        // The active client's pivot rows are present; silent client is hidden.
        $invoice_row = $this->findPivotRow($out, $active, 'Invoices by month');
        $this->assertCount(4, $invoice_row); // [name, jan, feb, mar]
        $this->assertStringContainsString('100.00', $invoice_row[1]); // Jan
        $this->assertSame('', $invoice_row[2]);                       // Feb empty
        $this->assertStringContainsString('300.00', $invoice_row[3]); // Mar

        $payment_row = $this->findPivotRow($out, $active, 'Payments by month');
        $this->assertSame('', $payment_row[1]);                       // Jan empty
        $this->assertStringContainsString('250.00', $payment_row[2]); // Feb
        $this->assertSame('', $payment_row[3]);                       // Mar empty

        $this->assertNull($this->findPivotRow($out, $silent, 'Invoices by month'));
        $this->assertNull($this->findPivotRow($out, $silent, 'Payments by month'));

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
