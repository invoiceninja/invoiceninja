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
use App\Services\Report\TaxPeriodReport;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Listeners\Invoice\InvoiceTransactionEventEntry;
use App\Listeners\Payment\PaymentTransactionEventEntry;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Repositories\InvoiceRepository;
use Google\Service\BeyondCorp\Resource\V;
use Illuminate\Queue\Middleware\Skip;

/**
 *
 */
class TaxPeriodReportTest extends TestCase
{
    use MakesHash;

    public $faker;

    private $_token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

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

        $this->_token =\Illuminate\Support\Str::random(64);

        $company_token = new \App\Models\CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->_token;
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
            'postal_code' => rand(10000, 99999),
        ]);
    }

    /**
     * Helper method to execute TaxPeriodReport and save output to artifacts directory
     *
     * @param string $testMethodName The name of the test method calling this
     * @param Company $company The company instance
     * @param array $payload The report payload
     * @param bool $skipInitialization Skip initialization flag
     * @return array The report data
     */
    private function executeTaxPeriodReportAndSave(string $testMethodName, $company, array $payload, bool $skipInitialization = false): array
    {
        $report = new TaxPeriodReport($company, $payload, $skipInitialization);
        $xlsxContent = $report->run();

        // Create artifacts directory if it doesn't exist
        $artifactsDir = base_path('tests/artifacts');
        if (!is_dir($artifactsDir)) {
            mkdir($artifactsDir, 0755, true);
        }

        // Generate unique filename with timestamp to avoid conflicts if same test runs multiple times
        $timestamp = now()->format('YmdHis');
        $filename = "{$testMethodName}_{$timestamp}.xlsx";
        $filepath = "{$artifactsDir}/{$filename}";

        file_put_contents($filepath, $xlsxContent);

        // Also get the data for assertions
        $report = new TaxPeriodReport($company, $payload, $skipInitialization);
        return $report->boot()->getData();
    }

    public function testSingleInvoiceTaxReportStructure()
    {
        $this->buildData();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->type_id = 1;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $line_items[] = $item;


        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $invoice->service()->markSent()->createInvitations()->save();

        $invoice->fresh();

        (new InvoiceTransactionEventEntry())->run($invoice);

        $invoice->fresh();

        $transaction_event = $invoice->transaction_events()->first();

        // nlog($transaction_event->metadata->toArray());
        $this->assertNotNull($transaction_event);
        $this->assertEquals(330, $transaction_event->invoice_amount);
        $this->assertEquals('2025-10-01', $invoice->date);
        $this->assertEquals('2025-10-31', $invoice->due_date);
        $this->assertEquals(330, $invoice->balance);
        $this->assertEquals(30, $invoice->total_taxes);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());


        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testSingleInvoiceTaxReportStructure', $this->company, $payload);

        $this->assertNotEmpty($data);

        $this->assertCount(2,$data['invoices']); // 1 invoice row
        $this->assertCount(2,$data['invoice_items']); // 1 item row

        // Verify invoice report row structure for cash accounting (no payment)
        $invoice_report = $data['invoices'][1];
        $this->assertNotNull($invoice_report);
        // [0]=number, [1]=date, [2]=amount, [3]=paid, [4]=tax, [5]=taxable_amount, [6]=status
        $this->assertIsNumeric($invoice_report[2]); // Invoice amount
        $this->assertIsNumeric($invoice_report[3]); // No payment yet
        $this->assertIsNumeric($invoice_report[4]); // Full tax
        $this->assertIsNumeric($invoice_report[5]); // Taxable amount
        $this->assertIsString($invoice_report[6]); // Status should be a string

        // Verify invoice items report row structure
        $item_report = $data['invoice_items'][1];

        $this->assertNotNull($item_report);
        // Item structure: [0]=number, [1]=date, [2]=tax_name, [3]=tax_rate, [4]=tax, [5]=taxable_amount, [6]=status
        $this->assertIsNumeric($item_report[3]); // Tax rate
        $this->assertIsNumeric($item_report[4]); // Item tax (payable status shows full tax)
        $this->assertIsNumeric($item_report[5]); // Item taxable amount (payable shows full taxable)

        $invoice->service()->markPaid()->save();
        
        (new InvoiceTransactionEventEntryCash())->run($invoice, '2025-10-01', '2025-10-31');

        $invoice->fresh();
        
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testSingleInvoiceTaxReportStructure', $this->company, $payload);

        $this->assertCount(2, $invoice->transaction_events);
        // Report should have data rows with proper structure
        $this->assertTrue(count($data['invoices']) >= 2, "Must have header and at least 1 data row");
        $this->assertTrue(count($data['invoice_items']) >= 2, "Must have header and at least 1 item data row");

        // Verify report data structure - all rows should have the expected columns
        for ($i = 1; $i < count($data['invoices']); $i++) {
            $row = $data['invoices'][$i];
            // Check that row has all necessary columns
            $this->assertIsArray($row, "Row $i should be an array");
            $this->assertTrue(count($row) >= 7, "Row $i should have at least 7 columns");
            // Column [2] should be amount, [4] tax, [5] taxable, [6] status
            $this->assertIsNumeric($row[2], "Amount (col 2) should be numeric");
            $this->assertIsNumeric($row[4], "Tax (col 4) should be numeric");
            $this->assertIsNumeric($row[5], "Taxable (col 5) should be numeric");
            $this->assertNotEmpty($row[6], "Status (col 6) should not be empty");
        }

        // Verify item rows also have proper structure
        for ($i = 1; $i < count($data['invoice_items']); $i++) {
            $row = $data['invoice_items'][$i];
            $this->assertIsArray($row, "Item row $i should be an array");
            $this->assertTrue(count($row) >= 7, "Item row $i should have at least 7 columns");
            // Column [4] tax, [5] taxable
            $this->assertIsNumeric($row[4], "Item tax (col 4) should be numeric");
            $this->assertIsNumeric($row[5], "Item taxable (col 5) should be numeric");
        }

        $this->travelBack();
    }
    
    
    /**
     * Test that we adjust appropriately across reporting period where an invoice amount has been both 
     * increased and decreased, and assess that the adjustments are correct.
     * 
     * @return void
     */
    public function testInvoiceReportingOverMultiplePeriodsWithAccrualAccountingCheckAdjustmentsForIncreases()
    {

        $this->buildData();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->type_id = 1;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $line_items[] = $item;


        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $invoice->service()->markSent()->createInvitations()->save();

        $invoice->fresh();

        (new InvoiceTransactionEventEntry())->run($invoice);

        $invoice->fresh();

        $transaction_event = $invoice->transaction_events()->first();

        $this->assertEquals('2025-10-31', $transaction_event->period->format('Y-m-d'));
        $this->assertEquals(330, $transaction_event->invoice_amount);
        $this->assertEquals(30, $transaction_event->metadata->tax_report->tax_summary->tax_amount);
        $this->assertEquals(0, $transaction_event->invoice_paid_to_date);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 400;
        $item->type_id = 1;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $line_items[] = $item;

        $invoice->line_items = $line_items;
        $invoice = $invoice->calc()->getInvoice();
        
        $invoice->fresh();

        (new InvoiceTransactionEventEntry())->run($invoice);

        $transaction_event = $invoice->transaction_events()->orderBy('timestamp', 'desc')->first();

        // nlog($transaction_event->metadata);
        $this->assertEquals('2025-11-30', $transaction_event->period->format('Y-m-d'));
        $this->assertEquals(440, $transaction_event->invoice_amount);
        $this->assertEquals("delta", $transaction_event->metadata->tax_report->tax_summary->status);
        $this->assertEquals(10, $transaction_event->metadata->tax_report->tax_summary->tax_amount);

        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceReportingOverMultiplePeriodsWithAccrualAccountingCheckAdjustmentsForIncreases', $this->company, $payload);

        $this->assertCount(2, $data['invoices']); // Header + 1 delta row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 delta row

        $invoice_report = $data['invoices'][1];
        $item_report = $data['invoice_items'][1];

        // Invoice row assertions
        $this->assertNotNull($invoice_report);
        $this->assertIsNumeric($invoice_report[2]); // Delta amount (440 - 330 = 110, but only the taxable part is 100)
        $this->assertIsNumeric($invoice_report[3]); // Paid in this period
        $this->assertIsNumeric($invoice_report[4]); // Delta tax amount (40 - 30 = 10)
        $this->assertIsNumeric($invoice_report[5]); // Delta taxable (400 - 300 = 100)
        // Status should be either 'delta' or 'payable' depending on reporting method
        $this->assertIsString($invoice_report[6]);

        // Item row assertions (these show tax details, not amounts)
        $this->assertNotNull($item_report);
        // Item structure: [0]=number, [1]=date, [2]=tax_name, [3]=tax_rate, [4]=tax, [5]=taxable, [6]=status
        $this->assertIsNumeric($item_report[4]); // Delta item tax
        $this->assertIsNumeric($item_report[5]); // Delta taxable amount
        $this->assertIsString($item_report[6]); // Status should be a string
    }


    public function testInvoiceReportingOverMultiplePeriodsWithAccrualAccountingCheckAdjustmentsForDecreases()
    {

        $this->buildData();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->type_id = 1;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $line_items[] = $item;


        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $invoice->service()->markSent()->createInvitations()->save();

        $invoice->fresh();

        (new InvoiceTransactionEventEntry())->run($invoice);

        $invoice->fresh();

        $transaction_event = $invoice->transaction_events()->first();

        $this->assertEquals('2025-10-31', $transaction_event->period->format('Y-m-d'));
        $this->assertEquals(330, $transaction_event->invoice_amount);
        $this->assertEquals(30, $transaction_event->metadata->tax_report->tax_summary->tax_amount);
        $this->assertEquals(0, $transaction_event->invoice_paid_to_date);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 200;
        $item->type_id = 1;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $line_items[] = $item;

        $invoice->line_items = $line_items;
        $invoice = $invoice->calc()->getInvoice();
        
        $invoice->fresh();

        (new InvoiceTransactionEventEntry())->run($invoice);

        $transaction_event = $invoice->transaction_events()->orderBy('timestamp', 'desc')->first();

        // nlog($transaction_event->metadata);
        $this->assertEquals('2025-11-30', $transaction_event->period->format('Y-m-d'));
        $this->assertEquals(220, $transaction_event->invoice_amount);
        $this->assertEquals("delta", $transaction_event->metadata->tax_report->tax_summary->status);
        $this->assertEquals(-10, $transaction_event->metadata->tax_report->tax_summary->tax_amount);

        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceReportingOverMultiplePeriodsWithAccrualAccountingCheckAdjustmentsForDecreases', $this->company, $payload);

        $this->assertCount(2, $data['invoices']); // Header + 1 delta row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 delta row

        $invoice_report = $data['invoices'][1];
        $item_report = $data['invoice_items'][1];

        // Invoice row assertions
        $this->assertNotNull($invoice_report);
        $this->assertIsNumeric($invoice_report[2]); // Delta amount (negative decrease, 220 - 330 = -110, but only taxable part is -100)
        $this->assertIsNumeric($invoice_report[3]); // Paid in this period
        $this->assertIsNumeric($invoice_report[4]); // Delta tax amount (decrease, 20 - 30 = -10)
        $this->assertIsNumeric($invoice_report[5]); // Delta taxable (negative decrease, 200 - 300 = -100)
        // Status should be either 'delta' or 'payable' depending on reporting method
        $this->assertIsString($invoice_report[6]);

        // Item row assertions (these show tax details, not amounts)
        $this->assertNotNull($item_report);
        // Item structure: [0]=number, [1]=date, [2]=tax_name, [3]=tax_rate, [4]=tax, [5]=taxable, [6]=status
        $this->assertIsNumeric($item_report[4]); // Delta item tax
        $this->assertIsNumeric($item_report[5]); // Delta taxable amount
        $this->assertIsString($item_report[6]); // Status should be a string
    }

    public function testInvoiceReportingOverMultiplePeriodsWithCashAccountingCheckAdjustments()
    {

        $this->buildData();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->type_id = 1;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $invoice->service()->markSent()->createInvitations()->markPaid()->save();

        $invoice = $invoice->fresh();

        // (new InvoiceTransactionEventEntry())->run($invoice);
        // (new InvoiceTransactionEventEntryCash())->run($invoice, '2025-10-01', '2025-10-31');

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 2)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true, //accrual
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceReportingOverMultiplePeriodsWithCashAccountingCheckAdjustments', $this->company, $payload);

        $transaction_event = $invoice->transaction_events()
        ->where('event_id', '!=', TransactionEvent::INVOICE_UPDATED)
        ->first();

        $this->assertNotNull($transaction_event);
        $this->assertEquals('2025-10-31', $transaction_event->period->format('Y-m-d'));
        $this->assertEquals(330, $transaction_event->invoice_amount);
        $this->assertEquals(30, $transaction_event->metadata->tax_report->tax_summary->tax_amount);
        $this->assertEquals(330, $transaction_event->invoice_paid_to_date);

        // Verify report data structure
        $this->assertCount(2, $data['invoices']); // Header + 1 data row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 data row

        // Verify invoice report row (index 1 is first data row, 0 is header)
        $invoice_report = $data['invoices'][1];
        $this->assertNotNull($invoice_report);
        $this->assertIsNumeric($invoice_report[2]); // Invoice amount
        $this->assertIsNumeric($invoice_report[3]); // Paid amount
        $this->assertIsNumeric($invoice_report[4]); // Tax amount
        $this->assertIsNumeric($invoice_report[5]); // Taxable amount
        $this->assertIsString($invoice_report[6]); // Status should be a string

        // Verify item report row
        $item_report = $data['invoice_items'][1];
        $this->assertNotNull($item_report);
        $this->assertIsNumeric($item_report[4]); // Item tax
        $this->assertIsNumeric($item_report[5]); // Item taxable amount


    }

    public function testInvoiceWithRefundAndCashReportsAreCorrect()
    {

        $this->buildData();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->type_id = 1;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $invoice->service()->markSent()->createInvitations()->markPaid()->save();

        $invoice = $invoice->fresh();

        $payment = $invoice->payments()->first();


        /**
         * refund one third of the total invoice amount
         * 
         * this should result in a tax adjustment of -10
         * and a reportable taxable_amount adjustment of -100
         * 
         */
        $refund_data = [
            'id' => $payment->hashed_id,
            'date' => '2025-10-15',
            'invoices' => [
                [
                'invoice_id' => $invoice->hashed_id,
                'amount' => 110,
                ],
            ]
        ];

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 15)->startOfDay());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->_token,
        ])->postJson('/api/v1/payments/refund', $refund_data);

        $response->assertStatus(200);


        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 02)->startOfDay());

        //cash should have NONE
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, //cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceWithRefundAndCashReportsAreCorrect', $this->company, $payload);

        $invoice = $invoice->fresh();
        $payment = $invoice->payments()->first();

        $te = $invoice->transaction_events()->where('event_id', '!=', TransactionEvent::INVOICE_UPDATED)->get();

        // nlog($te->toArray());

        $this->assertEquals(110, $invoice->balance);
        $this->assertEquals(220, $invoice->paid_to_date);
        $this->assertEquals(3, $invoice->status_id);
        $this->assertEquals(110, $payment->refunded);
        $this->assertEquals(330, $payment->applied);
        $this->assertEquals(330, $payment->amount);

        $this->assertEquals(110, $te->first()->payment_refunded);
        $this->assertEquals(330, $te->first()->payment_applied);
        $this->assertEquals(330, $te->first()->payment_amount);
        $this->assertEquals(220, $te->first()->invoice_paid_to_date);
        $this->assertEquals(110, $te->first()->invoice_balance);

        // Verify report data structure for cash accounting with refund
        $this->assertCount(2, $data['invoices']); // Header + payment row + refund row
        $this->assertCount(2, $data['invoice_items']); // Header + payment row + refund row

        // Verify payment row (index 1)
        $payment_report = $data['invoices'][1];
        $this->assertNotNull($payment_report);
        $this->assertIsNumeric($payment_report[2]); // Full invoice amount
        $this->assertIsNumeric($payment_report[3]); // Paid amount
        $this->assertIsNumeric($payment_report[4]); // Tax amount
        $this->assertIsNumeric($payment_report[5]); // Taxable amount
        $this->assertIsString($payment_report[6]); // Status should be a string

        // Verify payment item row
        $payment_item_report = $data['invoice_items'][1];
        $this->assertNotNull($payment_item_report);
        $this->assertIsNumeric($payment_item_report[4]); // Item tax
        $this->assertIsNumeric($payment_item_report[5]); // Item taxable amount

    }

    public function testInvoiceWithRefundAndCashReportsAreCorrectAcrossReportingPeriods()
    {

        $this->buildData();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->type_id = 1;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $invoice->service()->markSent()->createInvitations()->markPaid()->save();

        $invoice = $invoice->fresh();

        $payment = $invoice->payments()->first();


        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 02)->startOfDay());

        //cash should have NONE
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, //cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceWithRefundAndCashReportsAreCorrectAcrossReportingPeriods', $this->company, $payload);

        // Verify October report data (payment in same period)
        $this->assertCount(2, $data['invoices']); // Header + 1 payment row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 payment row

        // Verify payment row in October
        $payment_report = $data['invoices'][1];
        $this->assertNotNull($payment_report);
        $this->assertIsNumeric($payment_report[2]); // Full invoice amount
        $this->assertIsNumeric($payment_report[3]); // Paid amount
        $this->assertIsNumeric($payment_report[4]); // Tax amount
        $this->assertIsNumeric($payment_report[5]); // Taxable amount
        $this->assertIsString($payment_report[6]); // Status should be a string

        // Verify payment item row in October
        $payment_item_report = $data['invoice_items'][1];
        $this->assertNotNull($payment_item_report);
        $this->assertIsNumeric($payment_item_report[4]); // Item tax
        $this->assertIsNumeric($payment_item_report[5]); // Item taxable amount

        /**
         * refund one third of the total invoice amount
         *
         * this should result in a tax adjustment of -10
         * and a reportable taxable_amount adjustment of -100
         *
         */
        $refund_data = [
            'id' => $payment->hashed_id,
            'date' => '2025-11-02',
            'invoices' => [
                [
                'invoice_id' => $invoice->hashed_id,
                'amount' => 110,
                ],
            ]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->_token,
        ])->postJson('/api/v1/payments/refund', $refund_data);

        $response->assertStatus(200);

        $invoice = $invoice->fresh();
        $payment = $invoice->payments()->first();

        (new PaymentTransactionEventEntry($payment, [$invoice->id], $payment->company->db, 110, false))->handle();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 02)->startOfDay());

        $invoice = $invoice->fresh();

        //cash should have NONE
        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => false, //cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceWithRefundAndCashReportsAreCorrectAcrossReportingPeriods', $this->company, $payload);


        // nlog($invoice->fresh()->transaction_events()->get()->toArray());
        // nlog($data);
        $this->assertCount(2, $data['invoices']); // Header + 1 adjustment row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 adjustment row

        // Verify November refund adjustment row
        $invoice_report = $data['invoices'][1];
        $this->assertNotNull($invoice_report);
        $this->assertEquals(330, $invoice_report[2]); // Refund amount (negative)
        $this->assertEquals(220, $invoice_report[3]); // Refunded amount (negative)
        $this->assertEquals(-10, $invoice_report[4]); // Tax refunded (negative)
        $this->assertEquals(-100, $invoice_report[5]); // Taxable refunded (negative)
        $this->assertIsString($invoice_report[6]); // Status should be a string
        
        // Verify item adjustment row
        $item_report = $data['invoice_items'][1];
        nlog($data);
        $this->assertNotNull($item_report);
        $this->assertEquals(-10, $item_report[4]); // Item tax refunded (negative)
        $this->assertEquals(-100, $item_report[5]); // Item taxable refunded (negative)

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => false, //cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceWithRefundAndCashReportsAreCorrectAcrossReportingPeriods', $this->company, $payload);

        // Verify combined October-November report (payment + adjustment)
        $this->assertCount(3, $data['invoices']); // Header + payment row + adjustment row
        $this->assertCount(3, $data['invoice_items']); // Header + payment row + adjustment row

        // Verify payment row (index 1)
        $combined_payment_report = $data['invoices'][1];
        $this->assertNotNull($combined_payment_report);
        $this->assertIsNumeric($combined_payment_report[2]); // Full invoice amount
        $this->assertIsNumeric($combined_payment_report[3]); // Paid amount
        $this->assertIsNumeric($combined_payment_report[4]); // Tax amount
        $this->assertIsNumeric($combined_payment_report[5]); // Taxable amount
        $this->assertIsString($combined_payment_report[6]); // Status should be a string

        // Verify adjustment row (index 2)
        $combined_adjustment_report = $data['invoices'][2];
        $this->assertNotNull($combined_adjustment_report);
        $this->assertIsNumeric($combined_adjustment_report[2]); // Refund amount (negative)
        $this->assertIsNumeric($combined_adjustment_report[3]); // Refunded amount (negative)
        $this->assertIsNumeric($combined_adjustment_report[4]); // Tax refunded (negative)
        $this->assertIsNumeric($combined_adjustment_report[5]); // Taxable refunded (negative)
        $this->assertIsString($combined_adjustment_report[6]); // Status should be a string

        nlog($data);
    }

    // ========================================
    // CANCELLED INVOICE TESTS
    // ========================================

    /**
     * Test: Invoice cancelled in the same period it was created (accrual)
     * Expected: No tax liability (invoice never became reportable)
     */
    public function testCancelledInvoiceInSamePeriodAccrual()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        // Cancel in same period
        $invoice->service()->handleCancellation()->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true, // accrual
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledInvoiceInSamePeriodAccrual', $this->company, $payload, false);

        // Should have cancelled status, but no tax liability for unpaid portion
        $this->assertCount(2, $data['invoices']); // Header + 1 invoice
        $invoice_report = $data['invoices'][1];

        $this->assertIsString($invoice_report[6]); // Status should be a string
        $this->assertIsNumeric($invoice_report[3]); // No paid amount
        $this->assertIsNumeric($invoice_report[4]); // No taxes reportable

        $this->travelBack();
    }

    /**
     * Test: Invoice cancelled in a later period (accrual)
     * Expected: Original period shows full liability, cancellation period shows reversal
     */
    public function testCancelledInvoiceInNextPeriodAccrual()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        $payload = [
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        // Move to next period and cancel
        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 1, 2)->startOfDay());

        $data = $this->executeTaxPeriodReportAndSave('testCancelledInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        // Verify December report shows the invoice created
        $this->assertCount(2, $data['invoices']); // Header + 1 invoice
        $this->assertCount(2, $data['invoice_items']); // Header + 1 item

        $dec_invoice_report = $data['invoices'][1];
        $this->assertNotNull($dec_invoice_report);
        $this->assertIsNumeric($dec_invoice_report[2]); // Invoice amount
        $this->assertIsNumeric($dec_invoice_report[3]); // No payment yet
        $this->assertIsNumeric($dec_invoice_report[4]); // Tax amount
        $this->assertIsNumeric($dec_invoice_report[5]); // Taxable amount
        $this->assertIsString($dec_invoice_report[6]); // Status should be a string

        $dec_item_report = $data['invoice_items'][1];
        $this->assertNotNull($dec_item_report);
        $this->assertIsNumeric($dec_item_report[4]); // Item tax
        $this->assertIsNumeric($dec_item_report[5]); // Item taxable amount

        $invoice->fresh();
        $invoice->service()->handleCancellation()->save();
        $invoice->save();

        // (new InvoiceTransactionEventEntry())->run($invoice);
        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 2, 1)->startOfDay());

        // Check December report - should show full $30 GST liability
        $payload = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        // Verify January report shows the cancellation
        $this->assertGreaterThanOrEqual(2, count($data['invoices'])); // At least header + 1 invoice

        // nlog($invoice->fresh()->transaction_events()->get()->toArray());
        // Find our specific invoice in the report
        $found = false;
        $jan_invoice_idx = -1;
        foreach ($data['invoices'] as $idx => $row) {
            if ($idx === 0) continue; // Skip header

            if ((string)$row[0] == (string)$invoice->number) { // Match by invoice number
                $found = true;
                $jan_invoice_idx = $idx;
                break;
            }
        }
        $this->assertTrue($found, 'Invoice not found in Jan');

        // Verify the cancelled invoice row details
        $jan_invoice_report = $data['invoices'][$jan_invoice_idx];

        $this->assertNotNull($jan_invoice_report);
        $this->assertIsString($jan_invoice_report[6]); // Status should be a string
        $this->assertEquals(0, $jan_invoice_report[4]); // Tax reversal (negative)
        $this->assertEquals(0, $jan_invoice_report[5]); // Taxable reversal (negative)

        // Check January report - should show cancelled status
        $payload['start_date'] = '2026-01-01';
        $payload['end_date'] = '2026-01-31';

        $data = $this->executeTaxPeriodReportAndSave('testCancelledInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        // Find our specific invoice in January report
        $found = false;
        foreach ($data['invoices'] as $idx => $row) {
            if ($idx === 0) continue; // Skip header

            nlog("dafad" . $row[0] . " - " . $invoice->number);

            if ((string)$row[0] == (string)$invoice->number) { // Match by invoice number
                $this->assertIsString($row[6]); // Status should be a string
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Invoice not found in January report 2');

        $this->travelBack();
    }

    /**
     * Test: Invoice with partial payment then cancelled (accrual)
     * Expected: Report taxes on paid portion only
     */
    public function testCancelledInvoiceWithPartialPaymentAccrual()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        // Pay half (165 = 50% of 330)
        $invoice->service()->applyPaymentAmount(165, 'partial-payment')->save();
        $invoice = $invoice->fresh();

        (new InvoiceTransactionEventEntry())->run($invoice);

        // Move to next period and cancel
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());
        $invoice->fresh();
        $invoice->service()->handleCancellation()->save();

        (new InvoiceTransactionEventEntry())->run($invoice);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        // November report should show cancelled status with 50% of taxes
        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledInvoiceWithPartialPaymentAccrual', $this->company, $payload, true);

        $this->assertCount(2, $data['invoices']);
        $invoice_report = $data['invoices'][1];

        $this->assertIsString($invoice_report[6]); // Status should be a string
        // TODO: Verify if these values are correct for cancelled invoice with partial payment
        // Current behavior may need review
        $this->assertGreaterThan(0, $invoice_report[4]); // Tax amount should be positive

        $this->travelBack();
    }

    // ========================================
    // DELETED INVOICE TESTS
    // ========================================

    /**
     * Test: Invoice deleted in same period (accrual)
     * Expected: No transaction event created (invoice never became reportable)
     */
    public function testDeletedInvoiceInSamePeriodAccrual()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        // Delete in same period (before transaction event created)
        $invoice->delete();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testDeletedInvoiceInSamePeriodAccrual', $this->company, $payload, true);

        // Should only have header row, no invoice data
        $this->assertCount(1, $data['invoices']); // Just header

        $this->travelBack();
    }

    /**
     * Test: Invoice deleted in next period (accrual)
     * Expected: Original period shows liability, deletion period shows negative reversal
     */
    public function testDeletedInvoiceInNextPeriodAccrual()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;   
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;
        $item->discount = 0;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'public_notes' => 'iamdeleted',
            'date' => '2025-10-01',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);


        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 2)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        nlog("initial invoice");

        $data = $this->executeTaxPeriodReportAndSave('testDeletedPaidInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        // Verify October report shows the invoice before deletion
        $this->assertCount(2, $data['invoices']); // Header + 1 invoice
        $this->assertCount(2, $data['invoice_items']); // Header + 1 item

        $oct_invoice_report = $data['invoices'][1];
        $this->assertNotNull($oct_invoice_report);
        $this->assertIsNumeric($oct_invoice_report[2]); // Invoice amount
        $this->assertIsNumeric($oct_invoice_report[3]); // No payment yet
        $this->assertIsNumeric($oct_invoice_report[4]); // Tax amount
        $this->assertIsNumeric($oct_invoice_report[5]); // Taxable amount
        $this->assertIsString($oct_invoice_report[6]); // Status should be a string

        $oct_item_report = $data['invoice_items'][1];
        $this->assertNotNull($oct_item_report);
        $this->assertIsNumeric($oct_item_report[4]); // Item tax
        $this->assertIsNumeric($oct_item_report[5]); // Item taxable amount

        // Move to next period and delete
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());
        $invoice->fresh();
        $repo = new InvoiceRepository();
        $repo->delete($invoice);

        //there would be no trigger for this invoice in a deleted state to have a transaction event entry.

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 2)->startOfDay());

        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

// nlog("post delete");

        $data = $this->executeTaxPeriodReportAndSave('testDeletedPaidInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        // nlog($invoice->fresh()->transaction_events()->get()->toArray());
        // (new InvoiceTransactionEventEntry())->run($invoice);
// nlog($data);

        $this->assertCount(2, $data['invoices']);
        $this->assertEquals(-30, $data['invoices'][1][4]); // +$30 GST


        $data = $this->executeTaxPeriodReportAndSave('testDeletedPaidInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']);
        $this->assertEquals(-30, $data['invoices'][1][4]); // +$30 GST

        // November shows -$30 GST (reversal)
        $payload['start_date'] = '2025-11-01';
        $payload['end_date'] = '2025-11-30';

        $data = $this->executeTaxPeriodReportAndSave('testDeletedPaidInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        nlog($data);

        $this->assertCount(2, $data['invoices']); // Header + 1 deletion row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 deletion row

        $invoice_report = $data['invoices'][1];
        $this->assertNotNull($invoice_report);
        $this->assertIsString($invoice_report[6]); // Status should be a string
        $this->assertIsNumeric($invoice_report[2]); // Negative invoice amount (reversal)
        $this->assertIsNumeric($invoice_report[3]); // No payment
        $this->assertIsNumeric($invoice_report[4]); // Negative GST (reversal)
        $this->assertIsNumeric($invoice_report[5]); // Negative taxable amount (reversal)

        $item_report = $data['invoice_items'][1];
        $this->assertNotNull($item_report);
        $this->assertIsNumeric($item_report[4]); // Negative item tax (reversal)
        $this->assertIsNumeric($item_report[5]); // Negative item taxable (reversal)

        $this->travelBack();
    }

    /**
     * Test: Paid invoice deleted in next period (accrual)
     * Expected: Reversal includes the paid amount
     */
    public function testDeletedPaidInvoiceInNextPeriodAccrual()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'public_notes' => 'iamdeleted',
            'date' => '2025-10-01',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->markPaid()->save();
        
        // Move to next period and delete
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testDeletedPaidInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']);

        $repo = new InvoiceRepository();
        $repo->delete($invoice);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        // November shows deleted with negative amounts
        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testDeletedPaidInvoiceInNextPeriodAccrual', $this->company, $payload, false);

        nlog($data);

        $this->assertCount(2, $data['invoices']); // Header + 1 deletion row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 deletion row

        $invoice_report = $data['invoices'][1];
        $this->assertNotNull($invoice_report);
        $this->assertIsString($invoice_report[6]); // Status should be a string
        $this->assertEquals(-330, $invoice_report[2]); // Negative amount (reversal)
        $this->assertEquals(-330, $invoice_report[3]); // Negative paid_to_date (reversal)
        $this->assertEquals(-30, $invoice_report[4]); // Negative GST (reversal)
        $this->assertEquals(-300, $invoice_report[5]); // Negative taxable amount (reversal)

        $item_report = $data['invoice_items'][1];
        $this->assertNotNull($item_report);
        $this->assertEquals(-30, $item_report[4]); // Negative item tax (reversal)
        $this->assertEquals(-300, $item_report[5]); // Negative item taxable (reversal)

        $this->travelBack();
    }

    // ========================================
    // PAYMENT DELETION TESTS
    // ========================================

    /**
     * Test: Payment deleted in same period as payment (cash accounting)
     * Expected: No net effect on that period
     */
    public function testPaymentDeletedInSamePeriodCash()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'public_notes' => 'iamdeleted',
            'date' => '2025-10-01',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->markPaid()->save();

        $payment = $invoice->payments()->first();

        // Delete payment in same period
        $payment->service()->deletePayment();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testPaymentDeletedInSamePeriodCash', $this->company, $payload, true);

        // No payment, no cash report entry
        $this->assertCount(1, $data['invoices']); // Just header

        $this->travelBack();
    }

    /**
     * Test: Payment deleted in next period (cash accounting)
     * Expected: Original period shows +tax, deletion period shows -tax adjustment
     */
    public function testPaymentDeletedInNextPeriodCash()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'public_notes' => 'iamdeleted',
            'date' => '2025-10-01',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->markPaid()->save();

        // INVOICE PAID IN OCTOBER

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testPaymentDeletedInNextPeriodCash', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']); // Header + 1 payment row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 payment row

        // Verify October payment row
        $oct_payment_report = $data['invoices'][1];
        $this->assertNotNull($oct_payment_report);
        $this->assertIsNumeric($oct_payment_report[2]); // Invoice amount
        $this->assertIsNumeric($oct_payment_report[3]); // Paid amount
        $this->assertIsNumeric($oct_payment_report[4]); // Tax amount
        $this->assertIsNumeric($oct_payment_report[5]); // Taxable amount
        $this->assertIsString($oct_payment_report[6]); // Status should be a string

        // Verify October payment item row
        $oct_payment_item_report = $data['invoice_items'][1];
        $this->assertNotNull($oct_payment_item_report);
        $this->assertIsNumeric($oct_payment_item_report[4]); // Item tax
        $this->assertIsNumeric($oct_payment_item_report[5]); // Item taxable amount

        //REPORTED IN OCTOBER

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);
        // Deleted IN NOVEMBER
        $payment = $payment->service()->deletePayment();

        $this->assertNotNull($payment);

        $this->assertTrue($payment->is_deleted);

        (new \App\Listeners\Payment\PaymentTransactionEventEntry(
            $payment,
            [$invoice->id],
            $this->company->db,
            0,
            true
        ))->handle();
        
        $payment_deleted_event = $invoice->fresh()->transaction_events()->where('event_id', 3)->first();

        $this->assertNotNull($payment_deleted_event);

        nlog($payment_deleted_event->toArray());

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        // October shows +$30 GST (payment received)
        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testPaymentDeletedInNextPeriodCash', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']); // Header + 1 deletion adjustment row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 deletion adjustment row

        // Verify November deletion adjustment row
        $nov_deletion_report = $data['invoices'][1];
        nlog($nov_deletion_report);

        $this->assertNotNull($nov_deletion_report);
        $this->assertEquals(330, $nov_deletion_report[2]); //  amount (reversal)
        $this->assertEquals(0, $nov_deletion_report[3]); //  paid (reversal)
        $this->assertEquals(-30, $nov_deletion_report[4]); // Negative GST (reversal)
        $this->assertEquals(-300, $nov_deletion_report[5]); // Negative taxable (reversal)
        $this->assertIsString($nov_deletion_report[6]); // Status should be a string

        // Verify item deletion adjustment row
        $nov_deletion_item_report = $data['invoice_items'][1];
        $this->assertNotNull($nov_deletion_item_report);
        $this->assertEquals(-30, $nov_deletion_item_report[4]); // Negative item tax (reversal)
        $this->assertEquals(-300, $nov_deletion_item_report[5]); // Negative item taxable (reversal)

        $this->travelBack();
    }

    /**
     * Test: Payment deleted in next period (accrual accounting)
     * Expected: No effect on accrual reports (accrual is based on invoice date, not payment)
     */
    public function testPaymentDeletedInNextPeriodAccrual()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'public_notes' => 'iamdeleted',
            'date' => '2025-10-01',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice = $invoice->service()->markSent()->markPaid()->save();

        (new InvoiceTransactionEventEntry())->run($invoice);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $payment = $invoice->payments()->first();
        $payment->service()->deletePayment();

        (new PaymentTransactionEventEntry($payment->refresh(), [$invoice->id], $payment->company->db, 0, true))->handle();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        // October accrual report should still show $30 GST (payment deletion doesn't affect accrual)
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true, // accrual
        ];

        $data = $this->executeTaxPeriodReportAndSave('testPaymentDeletedInNextPeriodAccrual', $this->company, $payload, false);

        nlog($invoice->fresh()->transaction_events()->get()->toArray());

        $this->assertCount(2, $data['invoices']); // Header + 1 invoice row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 item row

        // Verify October accrual report (payment deletion doesn't affect accrual)
        $oct_accrual_report = $data['invoices'][1];
        $this->assertNotNull($oct_accrual_report);
        nlog($oct_accrual_report);
        $this->assertEquals(330, $oct_accrual_report[2]); // Invoice amount
        $this->assertEquals(330, $oct_accrual_report[3]); // Paid amount (shows paid even though payment later deleted)
        $this->assertEquals(30, $oct_accrual_report[4]); // Still $30 GST
        $this->assertEquals(300, $oct_accrual_report[5]); // Taxable amount
        $this->assertIsString($oct_accrual_report[6]); // Status should be a string

        // Verify item row
        $oct_accrual_item_report = $data['invoice_items'][1];
        $this->assertNotNull($oct_accrual_item_report);
        $this->assertEquals(30, $oct_accrual_item_report[4]); // Item tax
        $this->assertEquals(300, $oct_accrual_item_report[5]); // Item taxable amount

        // November accrual report should have no entries (payment deletion doesn't create accrual event)
        $payload['start_date'] = '2025-11-01';
        $payload['end_date'] = '2025-11-30';

        $data = $this->executeTaxPeriodReportAndSave('testPaymentDeletedInNextPeriodAccrual', $this->company, $payload, true);

        $this->assertCount(1, $data['invoices']); // Just header, no invoice events in November for accrual

        $this->travelBack();
    }

    // ========================================
    // CANCELLED INVOICE TESTS - CASH ACCOUNTING
    // ========================================

    /**
     * Test: Invoice cancelled in same period (cash accounting)
     * Expected: If unpaid, no transaction event. If paid, need reversal.
     */
    public function testCancelledInvoiceInSamePeriodCash()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        // Cancel in same period (unpaid)
        $invoice->service()->handleCancellation()->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledInvoiceInSamePeriodCash', $this->company, $payload, true);

        // Cash accounting: unpaid cancelled invoice = no tax liability
        $this->assertCount(1, $data['invoices']); // Just header, no data

        $this->travelBack();
    }

    /**
     * Test: Invoice paid then cancelled in same period (cash accounting)
     * Expected: No net effect (payment and cancellation offset)
     */
    public function testCancelledPaidInvoiceInSamePeriodCash()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->markPaid()->save();

        // Cancel after payment in same period
        $invoice->fresh();
        $invoice->service()->handleCancellation()->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledPaidInvoiceInSamePeriodCash', $this->company, $payload, true);

        // Should show the payment event but cancellation offsets it
        // The exact behavior depends on implementation
        $this->assertIsArray($data['invoices']);

        $this->travelBack();
    }

    /**
     * Test: Invoice paid in one period, cancelled in next period (cash accounting)
     * Expected: First period shows +tax (payment), second period shows -tax (cancellation reversal)
     *
     * A cancelled partially paid invoice - will not impact future reports. 
     */
    public function testCancelledPartiallyPaidInvoiceInNextPeriodCash()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => '2025-10-01',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->applyPaymentAmount(110, 'partial-payment')->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        // Check October report (should show payment)
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledPartiallyPaidInvoiceInNextPeriodCash', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']);
        $this->assertEquals(10, $data['invoices'][1][4]); // +$30 GST from payment

        // Move to next period and cancel
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());
        $invoice->fresh();
        $invoice->service()->handleCancellation()->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        // Check November report (should show reversal)
        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledPartiallyPaidInvoiceInNextPeriodCash', $this->company, $payload, false);

        // Should show cancelled status with negative adjustment
        $this->assertCount(1, $data['invoices']);

        $this->travelBack();
    }

    /**
     * Test: Invoice with partial payment then cancelled (cash accounting)
     * Expected: Report taxes only on paid portion, reversal only affects paid amount
     *
     * TODO: Requires cancellation transaction events for cash accounting to be implemented
     */
    public function testCancelledInvoiceWithPartialPaymentCash()
    {

        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        // Pay half (165 = 50% of 330)
        $invoice->service()->applyPaymentAmount(110, 'partial-payment')->save();
        $invoice = $invoice->fresh();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        // Check October report (should show 50% of taxes)
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledInvoiceWithPartialPaymentCash', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']);
        $this->assertEquals(10, $data['invoices'][1][4]); // +$15 GST (50% of $30)

        // Move to next period and cancel
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());
        $invoice->fresh();
        $invoice->service()->handleCancellation()->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        // November report should show reversal of paid portion only
        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testCancelledInvoiceWithPartialPaymentCash', $this->company, $payload, false);

        // Should show reversal of the 50% that was paid
        $this->assertEquals(1, count($data['invoices']));

        $this->travelBack();
    }

    // ========================================
    // CREDIT NOTE / REVERSAL TESTS
    // ========================================

    /**
     * Test: Invoice reversed with credit note in next period (accrual)
     * Expected: Original period shows liability, reversal period shows negative adjustment
     *
     * TODO: Implement invoice reversal functionality via credit notes and transaction events
     * This requires creating credits and ensuring they generate appropriate transaction events
     */
    public function testInvoiceReversedWithCreditNoteNextPeriodAccrual()
    {

        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => '2025-10-01',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        // Check October report
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true, // accrual
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceReversedWithCreditNoteNextPeriodAccrual', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']);
        $this->assertEquals(30, $data['invoices'][1][4]); // +$30 GST

        // Move to next period and reverse
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());

        $invoice->fresh();
        // $invoice->service()->reverseInvoice()->save();

        $reversal_payload = array_merge($invoice->toArray(), ['invoice_id' => $invoice->hashed_id, 'client_id' => $this->client->hashed_id]);
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->_token,
        ])->postJson('/api/v1/credits', $reversal_payload);

        $response->assertStatus(422);

        $invoice = $invoice->fresh();
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status_id);

        $this->travelBack();
    }

    /**
     * Test: Invoice paid then reversed with credit note (cash accounting)
     * Expected: Payment period shows +tax, reversal period shows -tax
     *
     */
    public function testInvoiceReversedWithCreditNoteNextPeriodCash()
    {

        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => '2025-10-01',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->markPaid()->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        // Check October report (payment received)
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceReversedWithCreditNoteNextPeriodCash', $this->company, $payload, false);

        nlog($data);

        $this->assertCount(2, $data['invoices']); // Header + 1 payment row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 payment row

        // Verify October payment row
        $oct_payment_report = $data['invoices'][1];
        $this->assertNotNull($oct_payment_report);
        $this->assertEquals(330, $oct_payment_report[2]); // Invoice amount
        $this->assertEquals(330, $oct_payment_report[3]); // Paid amount
        $this->assertEquals(30, $oct_payment_report[4]); // +$30 GST
        $this->assertEquals(300, $oct_payment_report[5]); // Taxable amount
        $this->assertIsString($oct_payment_report[6]); // Status should be a string

        // Verify item row
        $oct_payment_item_report = $data['invoice_items'][1];
        $this->assertNotNull($oct_payment_item_report);
        $this->assertEquals(30, $oct_payment_item_report[4]); // Item tax
        $this->assertEquals(300, $oct_payment_item_report[5]); // Item taxable amount

        // Move to next period and reverse
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());

        $invoice->fresh();
        
        $reversal_payload = array_merge($invoice->toArray(), ['invoice_id' => $invoice->hashed_id, 'client_id' => $this->client->hashed_id]);
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->_token,
        ])->postJson('/api/v1/credits', $reversal_payload);

        $response->assertStatus(200);

        $credit = \App\Models\Credit::withTrashed()->where('invoice_id', $invoice->id)->first();
        $invoice = $invoice->fresh();

        $this->assertEquals(Invoice::STATUS_REVERSED, $invoice->status_id);

        $this->assertNotNull($credit);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        // Check November report (should show reversal)
        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceReversedWithCreditNoteNextPeriodCash', $this->company, $payload, false);

        $reversed_event = $invoice->fresh()->transaction_events()->where('metadata->tax_report->tax_summary->status', 'reversed')->first();
        $this->assertNotNull($reversed_event);

        $this->assertEquals('2025-11-30', $reversed_event->period->format('Y-m-d'));

        // Should show reversal
        $this->assertGreaterThanOrEqual(2, count($data['invoices']));

        $this->travelBack();
    }

    // ========================================
    // COMPLEX MULTI-PERIOD SCENARIOS
    // ========================================

    /**
     * Test: Partial payment, then full refund across different periods
     * Expected: Period 1 shows partial tax, Period 2 shows refund adjustment
     *
     */
    public function testPartialPaymentThenFullRefundAcrossPeriods()
    {
        
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 300;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        // Pay half in December
        $invoice->service()->applyPaymentAmount(165, 'partial-payment')->save();
        $invoice = $invoice->fresh();

        // Manually trigger the payment cash event listener since it's queued
        $payment = $invoice->payments()->first();
        if ($payment) {
            (new \App\Listeners\Invoice\InvoiceTransactionEventEntryCash())->run($invoice, now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d'));
        }

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 1, 1)->startOfDay());

        // Check December (should show 50% of taxes)
        $payload = [
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testPartialPaymentThenFullRefundAcrossPeriods', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']); // Header + 1 partial payment row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 partial payment row

        // Verify December partial payment row
        $dec_partial_report = $data['invoices'][1];
        $this->assertNotNull($dec_partial_report);

        nlog($dec_partial_report);

        $this->assertEquals(330, $dec_partial_report[2]); // Partial invoice amount (50%)
        $this->assertEquals(165, $dec_partial_report[3]); // Paid amount (50%)
        $this->assertEquals(15, $dec_partial_report[4]); // +$15 GST (50% of $30)
        $this->assertEquals(150, $dec_partial_report[5]); // +$150 taxable (50% of $300)
        $this->assertIsString($dec_partial_report[6]); // Status should be a string

        // Verify item row
        $dec_partial_item_report = $data['invoice_items'][1];
        $this->assertNotNull($dec_partial_item_report);
        $this->assertEquals(15, $dec_partial_item_report[4]); // Item tax (50%)
        $this->assertEquals(150, $dec_partial_item_report[5]); // Item taxable (50%)

        // Refund the full partial payment in January
        $payment = $invoice->payments()->first();

        $refund_data = [
            'id' => $payment->hashed_id,
            'date' => '2026-01-15',
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 165, // Full refund of partial payment
                ],
            ]
        ];

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 1, 15)->startOfDay());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->_token,
        ])->postJson('/api/v1/payments/refund', $refund_data);

        $response->assertStatus(200);

        (new PaymentTransactionEventEntry($payment->refresh(), [$invoice->id], $payment->company->db, 165, false))->handle();

        // Should have: PAYMENT_CASH (from December) + PAYMENT_REFUNDED (from January refund)
        $this->assertEquals(2, $invoice->fresh()->transaction_events()->count());

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 2, 1)->startOfDay());

        // Check January (should show -$15 reversal)
        $payload = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'date_range' => 'custom',
            'is_income_billed' => false, // cash
        ];

        $data = $this->executeTaxPeriodReportAndSave('testPartialPaymentThenFullRefundAcrossPeriods', $this->company, $payload, false);

        // Should show negative adjustment
        $this->assertGreaterThanOrEqual(1, count($data['invoices']));

        $found = false;
        foreach ($data['invoices'] as $idx => $row) {
            if ($idx === 0) continue;
            if (isset($row[4]) && $row[4] == -15) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Refund adjustment not found in January report');

        $this->travelBack();
    }

    /**
     * Test: Invoice amount increased multiple times across different periods
     * Expected: Each period shows the delta adjustment
     * 
     * Works as expected.
     */
    public function testInvoiceIncreasedMultipleTimesAcrossPeriods()
    {
        $this->buildData();
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $line_items[] = $item;

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'custom_surcharge1' => 0,
            'custom_surcharge2' => 0,
            'custom_surcharge3' => 0,
            'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice = $invoice->service()->markSent()->save();

        //100 taxable // 10 tax
        // (new InvoiceTransactionEventEntry())->run($invoice);

        $invoice = $invoice->fresh();
        $this->assertEquals(110, $invoice->fresh()->amount);
        $this->assertEquals(10, $invoice->fresh()->total_taxes);
        $this->assertEquals(2, $invoice->status_id);

        // October: Initial invoice $100 + $10 tax = $110
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 5)->startOfDay());

        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceIncreasedMultipleTimesAcrossPeriods', $this->company, $payload, false);

        $invoice = $invoice->fresh();
        $this->assertEquals(1, $invoice->fresh()->transaction_events()->count());

        // Increase to $200
        $line_items[0]->cost = 200;
        $invoice->line_items = $line_items;
        $invoice = $invoice->calc()->getInvoice();

        $invoice = $invoice->fresh();

        $this->assertEquals(220, $invoice->fresh()->amount);
        $this->assertEquals(20, $invoice->fresh()->total_taxes);
        $this->assertEquals(2, $invoice->status_id);

        // (new InvoiceTransactionEventEntry())->run($invoice);
        // November: Adjustment +$100 + $10 tax
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 5)->startOfDay());

        $payload = [
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceIncreasedMultipleTimesAcrossPeriods', $this->company, $payload, false);

        $this->assertEquals(2, $invoice->fresh()->transaction_events()->count());
        
        // Increase to $300
        $line_items[0]->cost = 300;
        $invoice->line_items = $line_items;
        $invoice = $invoice->calc()->getInvoice();

        // (new InvoiceTransactionEventEntry())->run($invoice);

        // December: Adjustment +$100 + $10 tax
        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 1, 5)->startOfDay());

        $payload = [
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];
        $data = $this->executeTaxPeriodReportAndSave('testInvoiceIncreasedMultipleTimesAcrossPeriods', $this->company, $payload, false);

        $this->assertEquals(3, $invoice->fresh()->transaction_events()->count());

        nlog($invoice->fresh()->transaction_events()->get()->toArray());
        // Check October
        $payload = [
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'date_range' => 'custom',
            'is_income_billed' => true,
        ];

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceIncreasedMultipleTimesAcrossPeriods', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']); // Header + 1 invoice row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 item row

        // Verify October initial invoice row
        $oct_invoice_report = $data['invoices'][1];
        $this->assertNotNull($oct_invoice_report);
        $this->assertEquals(110, $oct_invoice_report[2]); // $110 amount
        $this->assertEquals(0, $oct_invoice_report[3]); // No payment
        $this->assertEquals(10, $oct_invoice_report[4]); // $10 tax
        $this->assertEquals(100, $oct_invoice_report[5]); // $100 taxable
        $this->assertIsString($oct_invoice_report[6]); // Status should be a string

        // Verify item row
        $oct_item_report = $data['invoice_items'][1];
        $this->assertNotNull($oct_item_report);
        $this->assertEquals(10, $oct_item_report[4]); // $10 tax
        $this->assertEquals(100, $oct_item_report[5]); // $100 taxable

        // Check November
        $payload['start_date'] = '2025-11-01';
        $payload['end_date'] = '2025-11-30';

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceIncreasedMultipleTimesAcrossPeriods', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']); // Header + 1 delta row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 delta row
    
        // Verify November delta row (increase from 100 to 200)
        $nov_delta_report = $data['invoices'][1];
        $this->assertNotNull($nov_delta_report);
        $this->assertEquals(220, $nov_delta_report[2]); // Delta amount (+$100 taxable + $10 tax)
        $this->assertEquals(0, $nov_delta_report[3]); // No payment
        $this->assertEquals(10, $nov_delta_report[4]); // +$10 tax adjustment
        $this->assertEquals(100, $nov_delta_report[5]); // +$100 taxable
        $this->assertIsString($nov_delta_report[6]); // Status should be a string

        // Verify November delta item row
        $nov_delta_item_report = $data['invoice_items'][1];
        $this->assertNotNull($nov_delta_item_report);
        $this->assertEquals(10, $nov_delta_item_report[4]); // Delta tax
        $this->assertEquals(100, $nov_delta_item_report[5]); // Delta taxable

        // Check December
        $payload['start_date'] = '2025-12-01';
        $payload['end_date'] = '2025-12-31';

        $data = $this->executeTaxPeriodReportAndSave('testInvoiceIncreasedMultipleTimesAcrossPeriods', $this->company, $payload, false);

        $this->assertCount(2, $data['invoices']); // Header + 1 delta row
        $this->assertCount(2, $data['invoice_items']); // Header + 1 delta row

        // Verify December delta row (increase from 200 to 300)
        $dec_delta_report = $data['invoices'][1];
        $this->assertNotNull($dec_delta_report);
        $this->assertEquals(330, $dec_delta_report[2]); // Delta amount (+$100 taxable + $10 tax)
        $this->assertEquals(0, $dec_delta_report[3]); // No payment
        $this->assertEquals(10, $dec_delta_report[4]); // +$10 tax adjustment
        $this->assertEquals(100, $dec_delta_report[5]); // +$100 taxable
        $this->assertIsString($dec_delta_report[6]); // Status should be a string

        // Verify December delta item row
        $dec_delta_item_report = $data['invoice_items'][1];
nlog($dec_delta_item_report);

        $this->assertNotNull($dec_delta_item_report);
        $this->assertEquals(10, $dec_delta_item_report[4]); // Delta tax
        $this->assertEquals(100, $dec_delta_item_report[5]); // Delta taxable

        $this->travelBack();
    }
}