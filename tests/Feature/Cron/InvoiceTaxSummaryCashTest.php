<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Cron;

use Carbon\Carbon;
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
use App\Jobs\Cron\InvoiceTaxSummary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

class InvoiceTaxSummaryCashTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;

    public $faker;

    public $company;

    public $user;

    public $account;

    public $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withoutExceptionHandling();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // reset
        parent::tearDown();
    }

    private function buildData(string $timezoneId = '15'): void
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
        $settings->timezone_id = $timezoneId;

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
            'notifications' => CompanySettings::notificationDefaults(),
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

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);
    }

    /**
     * Simulates the scenario from the bug:
     * - Company in Australia/Sydney (UTC+11) timezone
     * - Job runs on March 31 UTC (which is already April 1 in Sydney)
     * - Invoice was paid in March
     * - Cash entry should be created for period 2026-03-31
     *
     * Before the fix, processCompanyTaxSummary used now()->subMonth()
     * which on March 31 UTC = February, so it looked for Feb payments
     * and found nothing — resulting in zero cash entries for March.
     */
    public function testCashEntriesCreatedForCorrectMonthPositiveUtcOffset()
    {
        // timezone_id 105 = Australia/Sydney (UTC+10/+11)
        $this->buildData('105');

        // Create a paid invoice with a payment dated in March 2026
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => '2026-03-15',
            'terms' => '',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();
        $invoice->service()->markPaid()->save();

        $invoice->refresh();

        // Verify payment exists and backdate the paymentable pivot to March
        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment, 'Payment should exist after markPaid');

        // Set paymentable created_at to mid-March
        \DB::table('paymentables')
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->update(['created_at' => '2026-03-15 12:00:00']);

        // Simulate: job runs on March 31 at UTC 13:00
        // Australia/Sydney is UTC+11 in March (AEDT), so local time = April 1 00:00
        // This is when Sydney crosses midnight into the new month
        Carbon::setTestNow(Carbon::parse('2026-03-31 13:00:00', 'UTC'));

        // Clear any existing transaction events for this invoice
        TransactionEvent::where('invoice_id', $invoice->id)->delete();

        // Run the job's processing for this company
        $job = new InvoiceTaxSummary();
        $this->invokeProcessCompanyTaxSummary($job, $this->company);

        // Assert: cash entry should exist with period = 2026-03-31 (March)
        $cashEntry = TransactionEvent::where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->first();

        $this->assertNotNull($cashEntry, 'Cash transaction event should be created for March');
        $this->assertEquals('2026-03-31', $cashEntry->period->format('Y-m-d'), 'Cash entry period should be end of March');

        $this->account->delete();
    }

    /**
     * Test that negative UTC offset timezones (processed on April 1)
     * also get correct March cash entries.
     */
    public function testCashEntriesCreatedForNegativeUtcOffset()
    {
        // timezone_id 15 = America/New_York (UTC-5/-4)
        $this->buildData('15');

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => '2026-03-20',
            'terms' => '',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();
        $invoice->service()->markPaid()->save();

        $invoice->refresh();

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);

        \DB::table('paymentables')
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->update(['created_at' => '2026-03-20 18:00:00']);

        // Simulate: job runs on April 1 at UTC 04:00
        // New York is UTC-4 in April (EDT), so local time = April 1 00:00
        // This is when New York crosses midnight into the new month
        Carbon::setTestNow(Carbon::parse('2026-04-01 04:00:00', 'UTC'));

        TransactionEvent::where('invoice_id', $invoice->id)->delete();

        $job = new InvoiceTaxSummary();
        $this->invokeProcessCompanyTaxSummary($job, $this->company);

        $cashEntry = TransactionEvent::where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->first();

        $this->assertNotNull($cashEntry, 'Cash transaction event should be created for March');
        $this->assertEquals('2026-03-31', $cashEntry->period->format('Y-m-d'), 'Cash entry period should be end of March');

        $this->account->delete();
    }

    /**
     * Test that processCompanyTaxSummary skips mid-month midnight crossings.
     * Only month-end transitions should trigger processing.
     */
    public function testMidMonthMidnightCrossingSkipsProcessing()
    {
        // timezone_id 105 = Australia/Sydney (UTC+10/+11)
        $this->buildData('105');

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => '2026-03-10',
            'terms' => '',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();
        $invoice->service()->markPaid()->save();

        $invoice->refresh();

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);

        \DB::table('paymentables')
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->update(['created_at' => '2026-03-10 12:00:00']);

        TransactionEvent::where('invoice_id', $invoice->id)->delete();

        // Simulate: mid-month midnight crossing on March 15 in Sydney
        // Sydney is UTC+11 (AEDT), so March 14 UTC 13:00 = March 15 00:00 AEDT
        Carbon::setTestNow(Carbon::parse('2026-03-14 13:00:00', 'UTC'));

        $job = new InvoiceTaxSummary();
        $this->invokeProcessCompanyTaxSummary($job, $this->company);

        // No events should be created — mid-month crossing is skipped
        $eventCount = TransactionEvent::where('invoice_id', $invoice->id)->count();
        $this->assertEquals(0, $eventCount, 'No transaction events should be created for mid-month midnight crossing');

        $this->account->delete();
    }

    /**
     * Test that running the job twice for the same month-end does not
     * create duplicate PAYMENT_CASH entries (period-based dedup).
     */
    public function testNoDuplicateCashEntriesOnRerun()
    {
        // timezone_id 105 = Australia/Sydney
        $this->buildData('105');

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 1,
            'date' => '2026-03-15',
            'terms' => '',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_name1' => 'GST',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();
        $invoice->service()->markPaid()->save();

        $invoice->refresh();

        $payment = $invoice->payments()->first();
        $this->assertNotNull($payment);

        \DB::table('paymentables')
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->update(['created_at' => '2026-03-15 12:00:00']);

        TransactionEvent::where('invoice_id', $invoice->id)->delete();

        // Sydney month-end: March 31 UTC 13:00 = April 1 00:00 AEDT
        Carbon::setTestNow(Carbon::parse('2026-03-31 13:00:00', 'UTC'));

        $job = new InvoiceTaxSummary();

        // Run once — should create the cash entry
        $this->invokeProcessCompanyTaxSummary($job, $this->company);

        $cashCount = TransactionEvent::where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->count();
        $this->assertEquals(1, $cashCount, 'First run should create one cash entry');

        // Run again — should NOT create a duplicate
        $this->invokeProcessCompanyTaxSummary($job, $this->company);

        $cashCount = TransactionEvent::where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->count();
        $this->assertEquals(1, $cashCount, 'Second run should not create a duplicate cash entry');

        $this->account->delete();
    }

    /**
     * Use reflection to call the private processCompanyTaxSummary method.
     */
    private function invokeProcessCompanyTaxSummary(InvoiceTaxSummary $job, Company $company): void
    {
        $method = new \ReflectionMethod(InvoiceTaxSummary::class, 'processCompanyTaxSummary');
        $method->setAccessible(true);
        $method->invoke($job, $company);
    }

    private function buildLineItems(): array
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->product_key = 'test';
        $item->notes = 'test_product';

        $line_items[] = $item;

        return $line_items;
    }
}
