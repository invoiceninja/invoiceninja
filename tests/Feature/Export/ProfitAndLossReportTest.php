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
use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Models\User;
use App\Repositories\PaymentRepository;
use App\Services\Payment\PaymentApplicationDateResolver;
use App\Services\Report\ProfitLoss;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 *
 *  App\Services\Report\ProfitLoss
 */
class ProfitAndLossReportTest extends TestCase
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
            'email' => \Illuminate\Support\Str::random(32)."@gmail.com",
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'is_income_billed' => true,
            'include_tax' => false,
            'user_id' => $this->user->id,
        ];
    }

    public function testProfitLossInstance()
    {
        $this->buildData();

        $pl = new ProfitLoss($this->company, $this->payload);

        $this->assertInstanceOf(ProfitLoss::class, $pl);

        $this->account->delete();
    }

    public function testAllTimeIncludesRecordsBeforeTheFallbackDate(): void
    {
        $this->buildData();
        $this->payload['date_range'] = 'all_time';

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => false,
        ]);

        Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 100,
            'balance' => 100,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 0,
            'date' => '1999-01-02',
            'exchange_rate' => 1,
        ]);

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 25,
            'date' => '1998-01-01',
            'uses_inclusive_taxes' => true,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'exchange_rate' => 1,
        ]);

        $report = new ProfitLoss($this->company, $this->payload);
        $report->build();

        $this->assertEquals(100, $report->getIncome());
        $this->assertEquals(25, array_sum(array_column($report->getExpenseBreakDown(), 'total')));
    }

    public function testAllTimeCashAccountingStartsAtTheFirstPaymentApplication(): void
    {
        $this->buildData();
        $this->payload['date_range'] = 'all_time';
        $this->payload['is_income_billed'] = false;

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => false,
        ]);
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 0,
            'date' => '1999-01-01',
            'discount' => 0,
            'uses_inclusive_taxes' => false,
            'exchange_rate' => 1,
            'line_items' => [$item],
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markPaid()->save();

        $paymentable = Paymentable::query()
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice->id)
            ->firstOrFail();
        $paymentable->created_at = app(PaymentApplicationDateResolver::class)->encodeBusinessDate(
            '1999-01-02',
            $this->company->timezone()?->name ?: config('app.timezone'),
        );
        $paymentable->save();

        $report = new ProfitLoss($this->company, $this->payload);
        $report->build();

        $this->assertEquals(100, $report->getIncome());
    }

    public function testExpenseResolution()
    {
        $this->buildData();

        Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 121,
            'date' => now()->format('Y-m-d'),
            'uses_inclusive_taxes' => true,
            'tax_rate1' => 21,
            'tax_name1' => 'VAT',
            'calculate_tax_by_amount' => false,
            'exchange_rate' => 1,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expense_breakdown = $pl->getExpenseBreakDown();

        $this->assertEquals(100, array_sum(array_column($expense_breakdown, 'total')));
        $this->assertEquals(21, array_sum(array_column($expense_breakdown, 'tax')));

        $this->account->delete();

    }

    public function testMultiCurrencyInvoiceIncome()
    {
        $this->buildData();

        $settings = ClientSettings::defaults();
        $settings->currency_id = 2;

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'settings' => $settings
        ]);


        $client2 = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
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
            'exchange_rate' => 2
        ]);

        Invoice::factory()->create([
            'client_id' => $client2->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
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
            'exchange_rate' => 1
        ]);


        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(13.5, $pl->getIncome());
        $this->assertEquals(1.5, $pl->getIncomeTaxes());

        $this->account->delete();

    }

    public function testSimpleInvoiceIncome()
    {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->count(2)->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 11,
            'balance' => 11,
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
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(20.0, $pl->getIncome());
        $this->assertEquals(2, $pl->getIncomeTaxes());

        $this->account->delete();
    }

    public function testSimpleInvoiceIncomeWithInclusivesTaxes()
    {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->count(2)->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => 'GST',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => true,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(18.0, $pl->getIncome());
        $this->assertEquals(2, $pl->getIncomeTaxes());

        $this->account->delete();
    }

    public function testSimpleInvoiceIncomeWithForeignExchange()
    {
        $this->buildData();

        $settings = ClientSettings::defaults();
        $settings->currency_id = '2';

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'settings' => $settings,
        ]);

        Invoice::factory()->count(2)->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => 'GST',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => true,
            'exchange_rate' => 0.5,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(36.0, $pl->getIncome());
        $this->assertEquals(4, $pl->getIncomeTaxes());

        $this->account->delete();
    }

    public function testSimpleInvoicePaymentIncome()
    {
        $this->buildData();

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'is_income_billed' => false,
            'include_tax' => false,
            'user_id' => $this->user->id,
        ];

        $settings = ClientSettings::defaults();
        $settings->currency_id = '1';

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'settings' => $settings,
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => true,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;

        $i = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 0,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => true,
            'exchange_rate' => 1,
            'line_items' => [$item],
        ]);

        $i = $i->calc()->getInvoice();

        $i->service()->markPaid()->save();

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertEquals(10.0, $pl->getIncome());

        $this->account->delete();
    }

    public function testPaymentIncomeUsesInvoiceApplicationDate(): void
    {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => false,
        ]);
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'total_taxes' => 0,
            'date' => '2026-01-10',
            'discount' => 0,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'uses_inclusive_taxes' => false,
            'exchange_rate' => 1,
            'line_items' => [$item],
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markPaid()->save();
        $payment = $invoice->payments()->firstOrFail();
        $payment->date = '2026-01-10';
        $payment->exchange_rate = 1;
        $payment->save();
        $paymentable = Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice->id)
            ->firstOrFail();
        $paymentable->created_at = app(PaymentApplicationDateResolver::class)
            ->encodeBusinessDate('2026-02-05', $this->company->timezone()?->name ?: config('app.timezone'));
        $paymentable->save();

        $january = new ProfitLoss($this->company, [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'date_range' => 'custom',
            'is_income_billed' => false,
            'include_tax' => false,
            'user_id' => $this->user->id,
        ]);
        $january->build();

        $february = new ProfitLoss($this->company, [
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'date_range' => 'custom',
            'is_income_billed' => false,
            'include_tax' => false,
            'user_id' => $this->user->id,
        ]);
        $february->build();

        $this->assertSame(0.0, $january->getIncome());
        $this->assertSame(100.0, $february->getIncome());

        $this->account->delete();
    }

    #[DataProvider('deletedPaymentDateProvider')]
    public function testDeletedAndReplacedPaymentsUseOnlyTheActiveEventLineage(
        string $payment_date,
        string $source_period_start,
        string $source_period_end,
        float $expected_2026_income,
    ): void {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => false,
        ]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => true,
        ]);
        $invoice = $this->createZeroTaxInvoice($client, $payment_date, 100);
        $original_payment = $this->applyCashPayment($invoice, $payment_date);

        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-09-03 12:00:00', 'UTC'));
        $original_payment->service()->deletePayment();

        $deleted_payment = Payment::withTrashed()->findOrFail($original_payment->id);
        $source_event = TransactionEvent::query()
            ->where('payment_id', $original_payment->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->firstOrFail();
        $deletion_event = TransactionEvent::query()
            ->where('payment_id', $original_payment->id)
            ->where('event_id', TransactionEvent::PAYMENT_DELETED)
            ->firstOrFail();

        $this->assertTrue($deleted_payment->is_deleted);
        $this->assertSame($payment_date, data_get($source_event->payment_request, 'effective_date'));
        $this->assertSame('2026-09-30', $deletion_event->period->toDateString());
        $this->assertSame(-100.0, (float) $deletion_event->payment_applied);
        $this->assertSame(0.0, $this->cashIncome($source_period_start, $source_period_end));
        $this->assertSame(0.0, $this->cashIncome('2026-09-01', '2026-09-30'));

        $replacement_payment = $this->applyCashPayment($invoice->fresh(), $payment_date);

        $this->assertNotSame($original_payment->id, $replacement_payment->id);
        $this->assertSame(100.0, $this->cashIncome($source_period_start, $source_period_end));
        $this->assertSame(0.0, $this->cashIncome('2026-09-01', '2026-09-30'));
        $this->assertSame($expected_2026_income, $this->cashIncome('2026-01-01', '2026-12-31'));
        $this->assertTrue(TransactionEvent::query()->whereKey($source_event->id)->exists());
        $this->assertTrue(TransactionEvent::query()->whereKey($deletion_event->id)->exists());
        $this->assertTrue(TransactionEvent::query()
            ->where('payment_id', $replacement_payment->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->exists());

        $this->travelBack();
        $this->account->delete();
    }

    public static function deletedPaymentDateProvider(): array
    {
        return [
            'historical payment deleted in a later year' => [
                '2025-06-15',
                '2025-01-01',
                '2025-12-31',
                0.0,
            ],
            'payment deleted and replaced in the same year' => [
                '2026-01-15',
                '2026-01-01',
                '2026-12-31',
                100.0,
            ],
        ];
    }

    public function testRefundForNonDeletedPaymentRemainsInCashProfitLoss(): void
    {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => false,
        ]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => true,
        ]);
        $invoice = $this->createZeroTaxInvoice($client, '2025-06-15', 100);
        $payment = $this->applyCashPayment($invoice, '2025-06-15');

        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-09-03 12:00:00', 'UTC'));
        $payment = $payment->refund([
            'id' => $payment->id,
            'amount' => 100,
            'invoices' => [[
                'invoice_id' => $invoice->id,
                'amount' => 100,
            ]],
            'date' => '2026-09-03',
            'gateway_refund' => false,
            'email_receipt' => false,
        ]);

        $this->assertFalse($payment->fresh()->is_deleted);
        $this->assertSame(100.0, $this->cashIncome('2025-01-01', '2025-12-31'));
        $this->assertSame(-100.0, $this->cashIncome('2026-09-01', '2026-09-30'));

        $this->travelBack();
        $this->account->delete();
    }

    private function createZeroTaxInvoice(Client $client, string $date, float $amount): Invoice
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $amount;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
            'total_taxes' => 0,
            'date' => $date,
            'discount' => 0,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'uses_inclusive_taxes' => false,
            'exchange_rate' => 1,
            'line_items' => [$item],
        ]);
        $invoice = $invoice->calc()->getInvoice();

        return $invoice->service()->markSent()->save();
    }

    private function applyCashPayment(Invoice $invoice, string $date): Payment
    {
        return app(PaymentRepository::class)->save([
            'amount' => $invoice->amount,
            'client_id' => $invoice->client_id,
            'invoices' => [[
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
            ]],
            'date' => $date,
        ], PaymentFactory::create(
            $this->company->id,
            $this->user->id,
            $invoice->client_id,
        ));
    }

    private function cashIncome(string $start_date, string $end_date): float
    {
        return (new ProfitLoss($this->company, [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'date_range' => 'custom',
            'is_income_billed' => false,
            'include_tax' => false,
            'user_id' => $this->user->id,
        ]))->build()->getIncome();
    }

    public function testSimpleExpense()
    {
        $this->buildData();

        $e = Expense::factory()->create([
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $expense = $expenses[0];

        $this->assertEquals(10, $expense->total);

        $this->account->delete();
    }

    public function testSimpleExpenseAmountTax()
    {
        $this->buildData();

        $e = ExpenseFactory::create($this->company->id, $this->user->id);
        $e->amount = 10;
        $e->date = now()->format('Y-m-d');
        $e->calculate_tax_by_amount = true;
        $e->tax_amount1 = 10;
        $e->save();

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $expense = $expenses[0];

        $this->assertEquals(10, $expense->total);
        $this->assertEquals(10, $expense->tax);

        $this->account->delete();
    }

    public function testSimpleExpenseTaxRateExclusive()
    {
        $this->buildData();

        $e = ExpenseFactory::create($this->company->id, $this->user->id);
        $e->amount = 10;
        $e->date = now()->format('Y-m-d');
        $e->tax_rate1 = 10;
        $e->tax_name1 = 'GST';
        $e->uses_inclusive_taxes = false;
        $e->save();

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $expense = $expenses[0];

        $this->assertEquals(10, $expense->total);
        $this->assertEquals(1, $expense->tax);

        $this->account->delete();
    }

    public function testSimpleExpenseTaxRateInclusive()
    {
        $this->buildData();

        $e = ExpenseFactory::create($this->company->id, $this->user->id);
        $e->amount = 10;
        $e->date = now()->format('Y-m-d');
        $e->tax_rate1 = 10;
        $e->tax_name1 = 'GST';
        $e->uses_inclusive_taxes = false;
        $e->save();

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $expense = $expenses[0];

        $this->assertEquals(10, $expense->total);
        $this->assertEquals(1, $expense->tax);

        $this->account->delete();
    }

    public function testSimpleExpenseBreakdown()
    {
        $this->buildData();

        $e = Expense::factory()->create([
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $bd = $pl->getExpenseBreakDown();

        $this->assertEquals(array_sum(array_column($bd, 'total')), 10);

        $this->account->delete();
    }

    public function testSimpleExpenseCategoriesBreakdown()
    {
        $this->buildData();

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->user->id);
        $ec->name = 'Accounting';
        $ec->save();

        $e = Expense::factory()->create([
            'category_id' => $ec->id,
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->user->id);
        $ec->name = 'Fuel';
        $ec->save();

        $e = Expense::factory(2)->create([
            'category_id' => $ec->id,
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $expenses = $pl->getExpenses();

        $bd = $pl->getExpenseBreakDown();

        $this->assertEquals(array_sum(array_column($bd, 'total')), 30);

        $this->account->delete();
    }

    public function testCsvGeneration()
    {
        $this->buildData();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);

        Invoice::factory()->count(1)->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 10,
            'balance' => 10,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 10,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => 'GST',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => true,
        ]);

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->user->id);
        $ec->name = 'Accounting';
        $ec->save();

        $e = Expense::factory()->create([
            'category_id' => $ec->id,
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->user->id);
        $ec->name = 'Fuel';
        $ec->save();

        $e = Expense::factory(2)->create([
            'category_id' => $ec->id,
            'amount' => 10,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'exchange_rate' => 1,
            'currency_id' => $this->company->settings->currency_id,
        ]);

        $pl = new ProfitLoss($this->company, $this->payload);
        $pl->build();

        $this->assertNotNull($pl->getCsv());

        $this->account->delete();
    }
}
