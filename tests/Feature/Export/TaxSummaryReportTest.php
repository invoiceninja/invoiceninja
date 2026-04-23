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

    /**
     * Bug 2: Query mutation - the where('total_taxes', '>', 0) on line 112
     * permanently mutates $query, so the cursor loop on line 123 only
     * iterates invoices with total_taxes > 0, dropping tax-exempt invoices.
     *
     * This test creates one taxable PAID invoice and one tax-exempt PAID invoice.
     * The tax-exempt invoice should appear in the accrual section (it's a valid
     * sent invoice) but the query mutation bug causes it to be excluded from
     * the cursor loop entirely.
     */
    public function testBug2_QueryMutationExcludesTaxExemptInvoices()
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

        // Taxable invoice (has taxes) - SENT status
        $taxable = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);
        $taxable = $taxable->calc()->getInvoice();
        $taxable->service()->markSent()->save();

        // Tax-exempt invoice (no taxes) - PAID status
        $exempt = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 0,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);
        $exempt = $exempt->calc()->getInvoice();
        $exempt->service()->markPaid()->save();
        $exempt = $exempt->fresh();

        // Verify the exempt invoice is PAID and has total_taxes = 0
        $this->assertEquals(Invoice::STATUS_PAID, $exempt->status_id);
        $this->assertEquals(0, $exempt->total_taxes);

        // Run the report
        $report = new TaxSummaryReport($this->company, $this->payload);
        $csv = $report->run();

        // Access private $taxes via reflection
        $ref = new \ReflectionClass($report);
        $prop = $ref->getProperty('taxes');
        $prop->setAccessible(true);
        $taxes = $prop->getValue($report);

        // The cash section should recognize the exempt PAID invoice.
        // With Bug 2, cash_exempt_sales will be $0.00 because the query
        // mutation excludes tax-exempt invoices from the cursor loop.
        // The exempt invoice amount is $20 (2 line items x $10).
        $cash_exempt_raw = preg_replace('/[^0-9.\-]/', '', $taxes['cash_exempt_sales']);
        $cash_gross_raw = preg_replace('/[^0-9.\-]/', '', $taxes['cash_gross_sales']);

        // BUG PROOF: This assertion FAILS because the exempt invoice is
        // excluded from the loop due to query mutation.
        // cash_exempt_sales should be 20.00, but is 0.00.
        $this->assertGreaterThan(0, (float) $cash_exempt_raw, 'Bug 2: Tax-exempt PAID invoice is missing from cash section due to query mutation. cash_exempt_sales should include the $20 exempt invoice but is $0.');

        $this->account->delete();
    }

    /**
     * Bug 3: Cash gross sales double-counting - cash_gross_sales += $invoice->amount
     * runs inside the foreach($taxes) loop, so invoices with multiple tax rates
     * add their amount multiple times.
     *
     * This test creates a single PAID invoice with 3 tax rates.
     * The cash_gross_sales should equal the invoice amount once, not 3x.
     */
    public function testBug3_CashGrossSalesDoubleCountedPerTaxLine()
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

        // Single invoice with 3 tax rates - mark as PAID
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
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

        $invoice = $invoice->calc()->getInvoice();

        // Line items: 2 x $10 = $20 subtotal
        // Tax: GST 10% = $2, VAT 17.5% = $3.50, CA Sales Tax 5% = $1
        // Total = $26.50
        $invoice_amount = $invoice->amount;
        $this->assertEquals(26.5, $invoice_amount);

        $invoice->service()->markPaid()->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        // Run the report
        $report = new TaxSummaryReport($this->company, $this->payload);
        $csv = $report->run();

        // Access private $taxes via reflection
        $ref = new \ReflectionClass($report);
        $prop = $ref->getProperty('taxes');
        $prop->setAccessible(true);
        $taxes = $prop->getValue($report);

        $cash_gross_raw = (float) preg_replace('/[^0-9.\-]/', '', $taxes['cash_gross_sales']);

        // BUG PROOF: cash_gross_sales should be $26.50 (the invoice amount once).
        // With Bug 3, it will be $79.50 ($26.50 x 3 tax lines).
        $this->assertEquals(
            $invoice_amount,
            $cash_gross_raw,
            "Bug 3: cash_gross_sales is {$cash_gross_raw} but should be {$invoice_amount}. The invoice amount is being added once per tax line (3 tax rates = 3x inflation)."
        );

        $this->account->delete();
    }

    /**
     * Issue 4: When date_range is 'all', addDateRange sets start_date and end_date
     * to the string 'All available data'. The cash query then uses these strings
     * in a whereBetween on paymentables.created_at, which produces invalid SQL
     * or returns zero results.
     */
    public function testIssue4_AllDateRangeBreaksCashQuery()
    {
        $this->buildData();

        $this->payload = [
            'start_date' => '',
            'end_date' => '',
            'date_range' => 'all',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        // Create a PAID invoice
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markPaid()->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        // This should not throw an exception and should find the paid invoice
        $report = new TaxSummaryReport($this->company, $this->payload);
        $csv = $report->run();

        $ref = new \ReflectionClass($report);
        $prop = $ref->getProperty('taxes');
        $prop->setAccessible(true);
        $taxes = $prop->getValue($report);

        $cash_gross_raw = (float) preg_replace('/[^0-9.\-]/', '', $taxes['cash_gross_sales']);

        // The paid invoice should appear in cash results when date_range = 'all'
        $this->assertGreaterThan(0, $cash_gross_raw, 'Issue 4: date_range "all" should include all paid invoices in cash section, but cash_gross_sales is $0.');

        $this->account->delete();
    }

    /**
     * Issue 5: Refunded paymentables inflate cash totals because period_paid
     * sums paymentables.amount without subtracting paymentables.refunded.
     *
     * Creates an invoice, pays it, then partially refunds it.
     * The cash report should show net amount (paid - refunded), not gross paid.
     */
    public function testIssue5_RefundedPaymentablesInflateCashTotals()
    {
        $this->buildData();

        config(['queue.default' => 'sync']);

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        // Create and pay an invoice
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        // Invoice: 2 x $10 = $20 + 10% GST = $22
        $this->assertEquals(22, $invoice->amount);

        $invoice->service()->markPaid()->save();
        $invoice = $invoice->fresh();

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);

        // Refund $10 of the $22 payment
        $data = [
            'id' => $payment->id,
            'amount' => 10,
            'invoices' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 10,
                ]
            ],
            'date' => now()->format('Y-m-d'),
            'gateway_refund' => false,
            'email_receipt' => false,
        ];

        $payment->refund($data);

        // Run the report
        $report = new TaxSummaryReport($this->company, $this->payload);
        $csv = $report->run();

        $ref = new \ReflectionClass($report);
        $prop = $ref->getProperty('taxes');
        $prop->setAccessible(true);
        $taxes = $prop->getValue($report);

        $cash_gross_raw = (float) preg_replace('/[^0-9.\-]/', '', $taxes['cash_gross_sales']);

        // Net cash received: $22 paid - $10 refunded = $12
        // BUG PROOF: Without the fix, cash_gross_sales will be $22 (ignoring refund).
        $this->assertEquals(
            12.0,
            $cash_gross_raw,
            "Issue 5: cash_gross_sales is {$cash_gross_raw} but should be 12.00 (22 paid - 10 refunded). Refunds are not being subtracted."
        );

        config(['queue.default' => 'redis']);

        $this->account->delete();
    }

    /**
     * Archived (soft-deleted) payments with is_deleted=0 must be included
     * in cash accounting. The inner $period_paid query uses
     * whereHas('payment', ...) which applies Payment's SoftDeletes scope,
     * excluding archived payments and producing $0.00 amounts.
     */
    public function testArchivedPaymentIncludedInCashReport()
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

        // Create a taxable invoice and pay it
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        // 2 x $10 = $20 + 10% GST = $22
        $this->assertEquals(22, $invoice->amount);

        $invoice->service()->markPaid()->save();
        $invoice = $invoice->fresh();

        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);

        // Archive the payment (soft delete but NOT hard delete)
        $payment->deleted_at = now();
        $payment->is_deleted = 0;
        $payment->save();

        // Also archive the invoice (mirrors real-world scenario from user's data)
        $invoice->deleted_at = now();
        $invoice->is_deleted = 0;
        $invoice->save();

        // Run the report
        $report = new TaxSummaryReport($this->company, $this->payload);
        $csv = $report->run();

        $ref = new \ReflectionClass($report);
        $prop = $ref->getProperty('taxes');
        $prop->setAccessible(true);
        $taxes = $prop->getValue($report);

        $cash_gross_raw = (float) preg_replace('/[^0-9.\-]/', '', $taxes['cash_gross_sales']);

        // Archived payment should still count — cash_gross_sales should be $22
        $this->assertEquals(
            22.0,
            $cash_gross_raw,
            "Archived payment (deleted_at set, is_deleted=0) must be included in cash report. cash_gross_sales is {$cash_gross_raw} but should be 22.00."
        );

        // Verify the tax amount is also correct (not $0)
        $cash_map = $taxes['cash_map'];
        $this->assertArrayHasKey('GST 10%', $cash_map);

        $gst_raw = (float) preg_replace('/[^0-9.\-]/', '', $cash_map['GST 10%']['tax_amount']);
        $this->assertEquals(
            2.0,
            $gst_raw,
            "GST tax amount should be 2.00 but is {$gst_raw}. Archived payment taxes are being zeroed out."
        );

        $this->account->delete();
    }

    /**
     * Cash report should only include invoices with payments received
     * within the date range. The outer whereHas must constrain the
     * paymentable check to the current invoice, not any invoice on the
     * same payment.
     */
    public function testCashReportExcludesInvoicesPaidOutsideDateRange()
    {
        $this->buildData();

        // Invoice A: paid in January (inside range)
        $invoiceA = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => '2026-01-15',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);
        $invoiceA = $invoiceA->calc()->getInvoice();

        $this->travelTo(now()->setDate(2026, 1, 16));
        $invoiceA->service()->markPaid()->save();
        $invoiceA = $invoiceA->fresh();

        // Invoice B: paid in December (outside range)
        $this->travelTo(now()->setDate(2025, 12, 10));

        $invoiceB = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => '2025-12-01',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);
        $invoiceB = $invoiceB->calc()->getInvoice();
        $invoiceB->service()->markPaid()->save();
        $invoiceB = $invoiceB->fresh();

        $this->travelBack();

        // Report for January 2026 only
        $this->payload = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'date_range' => 'custom',
            'client_id' => $this->client->id,
            'report_keys' => [],
            'user_id' => $this->user->id,
        ];

        $report = new TaxSummaryReport($this->company, $this->payload);
        $csv = $report->run();

        $ref = new \ReflectionClass($report);
        $prop = $ref->getProperty('taxes');
        $prop->setAccessible(true);
        $taxes = $prop->getValue($report);

        $cash_gross_raw = (float) preg_replace('/[^0-9.\-]/', '', $taxes['cash_gross_sales']);

        // Only Invoice A ($22) should be in cash section, not Invoice B
        $this->assertEquals(
            22.0,
            $cash_gross_raw,
            "Cash report should only include Invoice A (\$22) paid in January. Got {$cash_gross_raw}. Invoice B paid in December should be excluded."
        );

        // Verify the detail rows only contain Invoice A
        $cash_invoice_map = $taxes['cash_invoice_map'];
        foreach ($cash_invoice_map as $row) {
            $this->assertStringContainsString(
                $invoiceA->number,
                $row['number'],
                "Cash detail should only contain Invoice A ({$invoiceA->number}), but found {$row['number']}"
            );
        }

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
