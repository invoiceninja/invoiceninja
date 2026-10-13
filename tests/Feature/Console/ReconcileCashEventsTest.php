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

namespace Tests\Feature\Console;

use App\DataMapper\CompanySettings;
use App\DataMapper\TransactionEventMetadata;
use App\Factory\InvoiceItemFactory;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Models\User;
use App\Services\Report\ReconcilePaymentApplicationTransactionEventsRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReconcileCashEventsTest extends TestCase
{
    use DatabaseTransactions;

    private Account $account;

    private User $user;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withoutExceptionHandling();
        $this->buildData();
    }

    public function testRunnerReportsNoProblemsWhenLegacyDateMatchesApplication(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-08');
        $payment->date = '2026-06-08';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-08');
        $amount = (float) $paymentable->amount;

        $this->createLegacyCashEvent($invoice, $payment, $amount, '2026-06-08', '2026-06-30');

        $result = $this->runner()->runForCompany($this->company, false, false);

        $this->assertSame(0, $result['summary']['problem_count']);
    }

    public function testRunnerReportsLegacyApplicationDateDriftWhenMetadataDateDiffers(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-08');
        $payment->date = '2026-06-08';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-08');
        $amount = (float) $paymentable->amount;

        $this->createLegacyCashEvent($invoice, $payment, $amount, '2026-06-09', '2026-06-30');

        $result = $this->runner()->runForCompany($this->company, false, false);

        $this->assertSame(1, $result['summary']['problem_count']);
        $this->assertSame('legacy_application_date_drift', $result['problems'][0]['issue']);
        $this->assertSame('eligible', $result['problems'][0]['legacy_remediation']);
    }

    public function testRunnerSyncsEligibleLegacyPaymentHistoryDateWhenFixing(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-08');
        $payment->date = '2026-06-08';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-08');
        $amount = (float) $paymentable->amount;

        $legacy = $this->createLegacyCashEvent($invoice, $payment, $amount, '2026-06-09', '2026-06-30');

        $result = $this->runner()->runForCompany($this->company, false, true);

        $this->assertSame(0, $result['summary']['problem_count']);
        $this->assertSame(1, $result['summary']['legacy_dates_synced']);

        $legacy->refresh();
        $this->assertSame(
            '2026-06-08',
            data_get($legacy->metadata, 'tax_report.payment_history.0.date'),
        );
    }

    public function testRunnerReportsAmbiguousLegacyEventsInsteadOfMissingCashLedger(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-08');
        $payment->date = '2026-06-08';
        $payment->saveQuietly();
        $this->setPaymentableApplicationDate($paymentable, '2026-06-08');

        $this->createLegacyCashEvent($invoice, $payment, 50.0, '2026-06-08', '2026-06-30');
        $this->createLegacyCashEvent($invoice, $payment, 60.0, '2026-06-09', '2026-06-30');

        $result = $this->runner()->runForCompany($this->company, false, false);

        $this->assertSame(1, $result['summary']['problem_count']);
        $this->assertSame('ambiguous_legacy_cash_events', $result['problems'][0]['issue']);
    }

    public function testRunnerMatchesLegacyEventByInvoiceAndMonthWhenPaymentIdIsZero(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-01');
        $payment->date = '2026-06-01';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-01');
        $amount = (float) $paymentable->amount;

        $legacy = $this->createLegacyCashEvent($invoice, $payment, $amount, '2026-06-09', '2026-06-30');
        $legacy->payment_id = 0;
        $legacy->saveQuietly();

        $result = $this->runner()->runForCompany($this->company, false, false);

        $this->assertSame('legacy_application_date_drift', $result['problems'][0]['issue']);
        $this->assertSame((int) $legacy->id, (int) $result['problems'][0]['ledger_event_id']);
    }

    public function testAttemptLegacyPaymentIdBackfillForEventWhenPaymentIdIsZero(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-01');
        $payment->date = '2026-06-01';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-01');
        $amount = (float) $paymentable->amount;

        $legacy = $this->createLegacyCashEvent($invoice, $payment, $amount, '2026-06-01', '2026-06-30');
        $legacy->payment_id = 0;
        $legacy->saveQuietly();

        $this->assertTrue($this->runner()->attemptLegacyPaymentIdBackfillForEvent($this->company, $legacy->fresh()));
        $legacy->refresh();
        $this->assertSame((int) $payment->id, (int) $legacy->payment_id);
    }

    public function testRunnerReconcilesV2DriftAfterRepeatedApplicationDateChanges(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-01-10');
        $writer = app(InvoiceTransactionEventEntryCash::class);
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-01-10');
        $writer->runForPaymentable($invoice, $paymentable);

        $writer->reconcileApplicationDateChange($invoice->id, $payment->id, '2026-01-10', '2026-01-20', [$paymentable->id]);
        $writer->reconcileApplicationDateChange($invoice->id, $payment->id, '2026-01-20', '2026-01-10', [$paymentable->id]);

        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-01-20');
        $payment->date = '2026-01-20';
        $payment->saveQuietly();

        $fixed = $this->runner()->runForCompany($this->company, true, false);

        $this->assertSame(0, $fixed['summary']['problem_count']);
        $this->assertSame(1, $fixed['summary']['remediated']);
        $this->assertSame(0, $fixed['summary']['fix_failed']);

        $source = $writer->findSourceEvent($invoice->id, $paymentable->id);
        $this->assertNotNull($source);
        $latest_apply = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->get()
            ->filter(fn (TransactionEvent $event): bool => (int) data_get($event->payment_request, 'source_event_id') === (int) $source->id
                && data_get($event->payment_request, 'direction') === 'apply')
            ->sortByDesc('id')
            ->first();
        $this->assertSame('2026-01-20', data_get($latest_apply?->payment_request, 'effective_date'));
    }

    public function testRunnerReportsV2DateDriftAndReconcilesOnFix(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-10');
        $writer = app(InvoiceTransactionEventEntryCash::class);
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-10');
        $writer->runForPaymentable($invoice, $paymentable);

        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-15');
        $payment->date = '2026-06-15';
        $payment->saveQuietly();

        $dry = $this->runner()->runForCompany($this->company, false, false);
        $this->assertSame('v2_date_drift', $dry['problems'][0]['issue']);

        $fixed = $this->runner()->runForCompany($this->company, true, false);
        $this->assertSame(0, $fixed['summary']['problem_count']);
        $this->assertSame(1, $fixed['summary']['remediated']);

        $source = $writer->findSourceEvent($invoice->id, $paymentable->id);
        $this->assertNotNull($source);
        $apply = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->get()
            ->first(fn (TransactionEvent $event): bool => data_get($event->payment_request, 'direction') === 'apply');
        $this->assertSame('2026-06-15', data_get($apply?->payment_request, 'effective_date'));
    }

    public function testRunnerPrefersExactPaymentableIdInPaymentHistory(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-08');
        $payment->date = '2026-06-08';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-08');
        $amount = (float) $paymentable->amount;

        $legacy = $this->createLegacyCashEvent($invoice, $payment, $amount, '2026-06-09', '2026-06-30');
        $metadata = $legacy->metadata->toArray();
        $metadata['tax_report']['payment_history'] = [
            [
                'paymentable_id' => 99999,
                'number' => (string) $payment->number,
                'date' => '2026-06-09',
                'amount' => $amount,
                'refunded' => 0,
            ],
            [
                'paymentable_id' => $paymentable->id,
                'number' => (string) $payment->number,
                'date' => '2026-06-09',
                'amount' => $amount,
                'refunded' => 0,
            ],
        ];
        $legacy->metadata = new TransactionEventMetadata($metadata);
        $legacy->saveQuietly();

        $result = $this->runner()->runForCompany($this->company, false, true);

        $this->assertSame(0, $result['summary']['problem_count']);
        $this->assertSame(1, $result['summary']['legacy_dates_synced']);
        $legacy->refresh();
        $this->assertSame('2026-06-08', data_get($legacy->metadata, 'tax_report.payment_history.1.date'));
        $this->assertSame('2026-06-09', data_get($legacy->metadata, 'tax_report.payment_history.0.date'));
    }

    public function testRunnerReportsUnresolvedHistoricalCashInsteadOfBackfill(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-02-01');
        $payment->date = '2026-02-01';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-02-01');
        $amount = (float) $paymentable->amount;

        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => 0,
            'credit_id' => 0,
            'client_balance' => 0,
            'client_paid_to_date' => 0,
            'client_credit_balance' => 0,
            'invoice_balance' => 0,
            'invoice_amount' => $invoice->amount,
            'invoice_partial' => 0,
            'invoice_paid_to_date' => $invoice->paid_to_date,
            'invoice_status' => $invoice->status_id,
            'payment_amount' => $amount,
            'payment_applied' => $amount,
            'payment_refunded' => 0,
            'payment_status' => $payment->status_id,
            'event_id' => TransactionEvent::PAYMENT_CASH,
            'timestamp' => now()->timestamp,
            'payment_request' => null,
            'metadata' => new TransactionEventMetadata([
                'tax_report' => [
                    'tax_summary' => ['taxable_amount' => $amount, 'tax_amount' => 0, 'status' => 'updated'],
                    'payment_history' => [[
                        'date' => '2026-01-15',
                        'amount' => $amount,
                        'refunded' => 0,
                    ]],
                ],
            ]),
            'period' => '2026-01-31',
        ]);

        $result = $this->runner()->runForCompany($this->company, true, false);

        $this->assertSame('unresolved_historical_cash', $result['problems'][0]['issue']);
        $this->assertSame(0, $result['summary']['remediated']);
    }

    public function testRunnerReportsCashInOtherPeriodInsteadOfMissingLedger(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-02-01');
        $payment->date = '2026-02-01';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-02-01');
        $amount = (float) $paymentable->amount;

        $this->createLegacyCashEvent($invoice, $payment, $amount, '2026-01-15', '2026-01-31');

        $result = $this->runner()->runForCompany($this->company, true, false);

        $this->assertSame(1, $result['summary']['problem_count']);
        $this->assertSame('cash_represented_in_other_period', $result['problems'][0]['issue']);
        $this->assertSame(0, $result['summary']['remediated']);
        $this->assertNull($this->writer()->findSourceEvent($invoice->id, $paymentable->id));
    }

    public function testArtisanCommandRejectsInvalidPaymentFilter(): void
    {
        config(['ninja.environment' => 'selfhost']);

        $exit_code = Artisan::call('ninja:reconcile-cash-events', ['--payment' => ['abc']]);

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('Invalid --payment', Artisan::output());
    }

    public function testArtisanCommandJsonOutputIsPureJson(): void
    {
        config(['ninja.environment' => 'selfhost']);

        Artisan::call('ninja:reconcile-cash-events', ['--json' => true]);
        $output = trim(Artisan::output());

        $this->assertStringStartsWith('{', $output);
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('totals', $decoded);
    }

    public function testArtisanCommandRefusesToRunOnHosted(): void
    {
        config(['ninja.environment' => 'hosted']);

        $exit_code = Artisan::call('ninja:reconcile-cash-events');

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('not available on Invoice Ninja hosted', Artisan::output());

        config(['ninja.environment' => 'selfhost']);
    }

    public function testArtisanCommandDryRunCompletesSuccessfully(): void
    {
        config(['ninja.environment' => 'selfhost']);
        [$invoice, $payment, $paymentable] = $this->makePaidInvoiceForCash('2026-06-08');
        $payment->date = '2026-06-08';
        $payment->saveQuietly();
        $paymentable = $this->setPaymentableApplicationDate($paymentable, '2026-06-08');
        $amount = (float) $paymentable->amount;
        $this->createLegacyCashEvent($invoice, $payment, $amount, '2026-06-09', '2026-06-30');

        $exit_code = Artisan::call('ninja:reconcile-cash-events');

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('Report only', Artisan::output());
    }

    private function runner(): ReconcilePaymentApplicationTransactionEventsRunner
    {
        return app(ReconcilePaymentApplicationTransactionEventsRunner::class);
    }

    private function writer(): InvoiceTransactionEventEntryCash
    {
        return app(InvoiceTransactionEventEntryCash::class);
    }

    private function buildData(): void
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32).'@gmail.com',
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
        ]);
    }

    /**
     * @return array{0: Invoice, 1: Payment, 2: Paymentable}
     */
    private function makePaidInvoiceForCash(string $application_date): array
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status_id' => Invoice::STATUS_SENT,
            'date' => $application_date,
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
        $invoice = $invoice->fresh();

        $payment = $invoice->payments()->firstOrFail();
        $paymentable = Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice->id)
            ->firstOrFail();

        TransactionEvent::query()->where('invoice_id', $invoice->id)->delete();

        return [$invoice, $payment, $paymentable];
    }

    private function setPaymentableApplicationDate(Paymentable $paymentable, string $date): Paymentable
    {
        Paymentable::query()
            ->whereKey($paymentable->id)
            ->update(['created_at' => $date.' 12:00:00']);

        return $paymentable->fresh();
    }

    private function createLegacyCashEvent(
        Invoice $invoice,
        Payment $payment,
        float $amount,
        string $payment_history_date,
        string $period,
    ): TransactionEvent {
        return TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'client_balance' => 0,
            'client_paid_to_date' => 0,
            'client_credit_balance' => 0,
            'invoice_balance' => 0,
            'invoice_amount' => $invoice->amount,
            'invoice_partial' => 0,
            'invoice_paid_to_date' => $invoice->paid_to_date,
            'invoice_status' => $invoice->status_id,
            'payment_amount' => $amount,
            'payment_applied' => $amount,
            'payment_refunded' => 0,
            'payment_status' => $payment->status_id,
            'event_id' => TransactionEvent::PAYMENT_CASH,
            'timestamp' => now()->timestamp,
            'payment_request' => null,
            'metadata' => new TransactionEventMetadata([
                'tax_report' => [
                    'tax_summary' => [
                        'taxable_amount' => $amount,
                        'tax_amount' => 0,
                        'status' => 'updated',
                    ],
                    'payment_history' => [[
                        'number' => (string) $payment->number,
                        'date' => $payment_history_date,
                        'amount' => $amount,
                        'refunded' => 0,
                    ]],
                ],
            ]),
            'period' => $period,
        ]);
    }

    private function buildLineItems(): array
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->product_key = 'test';
        $item->notes = 'test_product';

        return [$item];
    }
}
