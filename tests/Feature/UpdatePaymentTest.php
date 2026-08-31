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

namespace Tests\Feature;

use App\Events\Payment\PaymentApplicationDateChanged;
use App\Factory\InvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Jobs\EDocument\RecordFranceEReportingScopeInvalidation;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Listeners\Payment\ReconcilePaymentApplicationDateChange;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\PaymentController
 */
class UpdatePaymentTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        $this->makeTestData();
        $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testUpdatingPaymentDateMovesDerivedApplicationDateAndSignalsReportingReconciliation(): void
    {
        $this->enableFranceReporting();
        [$payment, $paymentable] = $this->createPaymentApplication('2026-07-01', '2026-07-01');
        Event::fake([PaymentApplicationDateChanged::class]);

        $this->updatePaymentDate($payment, '2026-07-05')->assertStatus(200);

        $this->assertSame('2026-07-05', $payment->fresh()->date);
        $this->assertSame('2026-07-05', $this->applicationDate($paymentable));
        Event::assertDispatched(
            PaymentApplicationDateChanged::class,
            function (PaymentApplicationDateChanged $event) use ($payment, $paymentable): bool {
                $this->assertSame(
                    ['payment_id', 'db', 'old_date', 'new_date', 'paymentable_ids'],
                    array_keys(get_object_vars($event)),
                );

                return $event->payment_id === $payment->id
                    && $event->db === $this->company->db
                    && $event->old_date === '2026-07-01'
                    && $event->new_date === '2026-07-05'
                    && $event->paymentable_ids === [$paymentable->id];
            },
        );
    }

    public function testUpdatingPaymentDatePreservesAnIndependentApplicationDate(): void
    {
        $this->enableFranceReporting();
        [$payment, $paymentable] = $this->createPaymentApplication('2026-07-01', '2026-07-03');
        Event::fake([PaymentApplicationDateChanged::class]);

        $this->updatePaymentDate($payment, '2026-07-02')->assertStatus(200);

        $this->assertSame('2026-07-02', $payment->fresh()->date);
        $this->assertSame('2026-07-03', $this->applicationDate($paymentable));
        Event::assertNotDispatched(PaymentApplicationDateChanged::class);
    }

    public function testUpdatingPaymentRejectsAnInvalidDate(): void
    {
        [$payment, $paymentable] = $this->createPaymentApplication('2026-07-01', '2026-07-01');
        $this->withExceptionHandling();

        $this->updatePaymentDate($payment, 'not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');

        $this->assertSame('2026-07-01', $payment->fresh()->date);
        $this->assertSame('2026-07-01', $this->applicationDate($paymentable));
    }

    public function testNoOpPaymentDateUpdateDoesNotTouchApplicationOrDispatchSignal(): void
    {
        $this->enableFranceReporting();
        [$payment, $paymentable] = $this->createPaymentApplication('2026-07-01', '2026-07-01');
        $original_paymentable = $this->findPaymentable($paymentable->id);
        Event::fake([PaymentApplicationDateChanged::class]);

        $this->updatePaymentDate($payment, '2026-07-01')->assertStatus(200);

        $current_paymentable = $this->findPaymentable($paymentable->id);
        $this->assertSame($original_paymentable->created_at, $current_paymentable->created_at);
        $this->assertSame($original_paymentable->updated_at, $current_paymentable->updated_at);
        Event::assertNotDispatched(PaymentApplicationDateChanged::class);
    }

    public function testPaymentDateUpdateMovesMultipleInvoiceAndCreditApplications(): void
    {
        $payment = $this->createPayment('2026-07-01', 30, 30);
        $second_invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $credit = Credit::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $paymentables = [
            $this->createPaymentable($payment, $this->invoice->id, 'invoices', '2026-07-01'),
            $this->createPaymentable($payment, $second_invoice->id, 'invoices', '2026-07-01'),
            $this->createPaymentable($payment, $credit->id, Credit::class, '2026-07-01'),
        ];

        $this->updatePaymentDate($payment, '2026-07-05')->assertStatus(200);

        $this->assertSame('2026-07-05', $payment->fresh()->date);
        foreach ($paymentables as $paymentable) {
            $this->assertSame('2026-07-05', $this->applicationDate($paymentable));
        }
    }

    public function testPaymentDateUpdateMovesSoftDeletedApplication(): void
    {
        [$payment, $paymentable] = $this->createPaymentApplication('2026-07-01', '2026-07-01');
        $paymentable->delete();

        $this->updatePaymentDate($payment, '2026-07-05')->assertStatus(200);

        $paymentable = $this->findPaymentable($paymentable->id);
        $this->assertNotNull($paymentable->deleted_at);
        $this->assertSame('2026-07-05', $this->applicationDate($paymentable));
    }

    public function testApplicationAddedDuringDateUpdateKeepsItsOwnApplicationDate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00', 'UTC'));

        try {
            $payment = $this->createPayment('2026-07-01', 20, 10);
            $existing_paymentable = $this->createPaymentable($payment, $this->invoice->id, 'invoices', '2026-07-01', 10);
            $new_invoice = $this->createSentInvoice();

            $this->updatePaymentDate($payment, '2026-07-05', [
                'invoices' => [
                    [
                        'invoice_id' => $new_invoice->hashed_id,
                        'amount' => 5,
                    ],
                ],
            ])->assertStatus(200);

            $new_paymentable = Paymentable::withTrashed()
                ->where('payment_id', $payment->id)
                ->where('paymentable_id', $new_invoice->id)
                ->where('paymentable_type', 'invoices')
                ->firstOrFail();

            $this->assertSame('2026-07-05', $this->applicationDate($existing_paymentable));
            $this->assertSame('2026-07-10', $this->applicationDate($new_paymentable));
            $this->assertSame(2, Paymentable::withTrashed()->where('payment_id', $payment->id)->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function testPaymentDateUpdateHandlesCompanyTimezoneAtDstBoundary(): void
    {
        $timezone = app('timezones')->firstWhere('name', 'America/New_York');
        $this->assertNotNull($timezone);

        $settings = $this->company->settings;
        $settings->timezone_id = $timezone->id;
        $this->company->settings = $settings;
        $this->company->save();
        $this->company = $this->company->fresh();

        [$payment, $paymentable] = $this->createPaymentApplication('2026-03-08', '2026-03-08');

        $this->updatePaymentDate($payment, '2026-03-09')->assertStatus(200);

        $this->assertSame('2026-03-09', $payment->fresh()->date);
        $this->assertSame('2026-03-09', $this->applicationDate($paymentable));
    }

    public function testNegativeOffsetCrossMonthApiUpdateMovesCashAndDispatchesFranceRuntimeMovements(): void
    {
        $timezone = app('timezones')->firstWhere('name', 'America/New_York');
        $this->assertNotNull($timezone);
        $settings = $this->company->settings;
        $settings->timezone_id = $timezone->id;
        $settings->france_reporting_enabled = true;
        $settings->currency_id = '3';
        $this->company->settings = $settings;
        $this->company->save();
        $this->company = $this->company->fresh();
        $this->client->classification = 'individual';
        $this->client->group_settings_id = null;
        $this->client->save();
        $this->client = $this->client->fresh();
        $this->invoice->status_id = Invoice::STATUS_PAID;
        $this->invoice->paid_to_date = $this->invoice->amount;
        $this->invoice->balance = 0;
        $line_items = $this->invoice->line_items;

        foreach ($line_items as $line_item) {
            $line_item->type_id = (string) Product::PRODUCT_TYPE_SERVICE;
        }

        $this->invoice->line_items = $line_items;
        $this->invoice->save();
        [$payment, $paymentable] = $this->createPaymentApplication('2026-08-31', '2026-08-31');
        TransactionEvent::query()->where('invoice_id', $this->invoice->id)->delete();

        (new InvoiceTransactionEventEntryCash())->run($this->invoice, '2026-08-01', '2026-08-31');
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $this->invoice->id,
            $paymentable->id,
            (string) $paymentable->amount,
            '2026-08-31',
        ))->handle();

        Event::fake([PaymentApplicationDateChanged::class]);
        $this->updatePaymentDate($payment, '2026-09-01')->assertStatus(200);
        $event = Event::dispatched(PaymentApplicationDateChanged::class)->first()[0];
        app(ReconcilePaymentApplicationDateChange::class)->handle($event);

        $cashEvents = TransactionEvent::query()
            ->where('invoice_id', $this->invoice->id)
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->get();
        $franceMovements = TransactionEvent::query()
            ->where('invoice_id', $this->invoice->id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $cashEvents);
        $source_event = $cashEvents->first(fn(TransactionEvent $event): bool => ! data_get($event->payment_request, 'tax_correction_kind'));
        $remove_event = $cashEvents->first(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'direction') === 'remove');
        $apply_event = $cashEvents->first(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'direction') === 'apply');
        $this->assertSame('2026-08-31', $source_event->period->toDateString());
        $this->assertSame('2026-08-31', $remove_event->period->toDateString());
        $this->assertSame('2026-09-30', $apply_event->period->toDateString());
        $this->assertSame('2026-09-01', data_get($apply_event->metadata, 'tax_report.payment_history.0.date'));
        $this->assertCount(3, $franceMovements);
        $this->assertSame(
            [
                FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
                FrancePaymentApplicationRecorder::MOVEMENT_DATE_REVERSED,
                FrancePaymentApplicationRecorder::MOVEMENT_DATE_REAPPLIED,
            ],
            $franceMovements
                ->map(fn(TransactionEvent $event): string => data_get($event->payment_request, 'movement_type'))
                ->all(),
        );
        $this->assertNotSame(
            data_get($franceMovements[1]->payment_request, 'operation_key'),
            data_get($franceMovements[2]->payment_request, 'operation_key'),
        );
    }

    public function testPaymentDateUpdateSkipsFranceFactCaptureWhenReportingIsDisabled(): void
    {
        [$payment, $paymentable] = $this->createPaymentApplication('2026-08-31', '2026-08-31');
        $this->mock(FrancePaymentApplicationRecorder::class)
            ->shouldReceive('recordApplicationDateChange')
            ->never();

        $this->updatePaymentDate($payment, '2026-09-01')->assertStatus(200);

        $this->assertSame('2026-09-01', $payment->fresh()->date);
        $this->assertSame('2026-09-01', $this->applicationDate($this->findPaymentable($paymentable->id)));
    }

    public function test_source_reconciliation_detects_an_api_payment_type_update(): void
    {
        $this->enableFranceReporting();
        $this->client->classification = 'individual';
        $this->client->saveQuietly();
        $this->invoice->status_id = Invoice::STATUS_PAID;
        $this->invoice->balance = 0;
        $lineItems = $this->invoice->line_items;

        foreach ($lineItems as $lineItem) {
            $lineItem->type_id = (string) Product::PRODUCT_TYPE_SERVICE;
        }

        $this->invoice->line_items = $lineItems;
        $this->invoice->saveQuietly();
        [$payment, $paymentable] = $this->createPaymentApplication('2026-09-25', '2026-09-25');
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $this->invoice->id,
            $paymentable->id,
            (string) $paymentable->amount,
            '2026-09-25',
        ))->handle();
        $before = TransactionEvent::query()
            ->where('invoice_id', $this->invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->where('payment_request->family', 'payment')
            ->count();

        $this->updatePaymentDate($payment, '2026-09-25', [
            'type_id' => PaymentType::ACH,
        ])->assertStatus(200);

        $this->assertSame(PaymentType::ACH, $payment->fresh()->type_id);
        $this->assertSame($before, TransactionEvent::query()
            ->where('invoice_id', $this->invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->where('payment_request->family', 'payment')
            ->count());
        (new RecordFranceEReportingScopeInvalidation(
            companyId: $this->company->id,
            db: $this->company->db,
            invalidationKey: 'test-source-reconciliation',
            reconcileRecentSourceState: true,
            sourceReconciliationSince: '2026-01-01T00:00:00+00:00',
        ))->handle();
        $this->assertGreaterThan($before, TransactionEvent::query()
            ->where('invoice_id', $this->invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->where('payment_request->family', 'payment')
            ->count());
    }

    /**
     * @return array{0: Payment, 1: Paymentable}
     */
    private function createPaymentApplication(string $payment_date, string $application_date): array
    {
        $payment = $this->createPayment($payment_date);
        $paymentable = $this->createPaymentable(
            $payment,
            $this->invoice->id,
            'invoices',
            $application_date,
        );

        return [$payment, $paymentable];
    }

    private function createPayment(string $date, float $amount = 10, float $applied = 10): Payment
    {
        return Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Payment::STATUS_COMPLETED,
            'amount' => $amount,
            'applied' => $applied,
            'date' => $date,
        ]);
    }

    private function createPaymentable(
        Payment $payment,
        int $paymentable_id,
        string $paymentable_type,
        string $application_date,
        float $amount = 10,
    ): Paymentable {
        $paymentable = new Paymentable();
        $paymentable->payment_id = $payment->id;
        $paymentable->paymentable_id = $paymentable_id;
        $paymentable->paymentable_type = $paymentable_type;
        $paymentable->amount = $amount;
        $paymentable->refunded = 0;
        $paymentable->created_at = $application_date;
        $paymentable->updated_at = $application_date;
        $paymentable->save();

        $paymentable = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $paymentable_id)
            ->where('paymentable_type', $paymentable_type)
            ->latest('id')
            ->firstOrFail();

        return $paymentable;
    }

    private function createSentInvoice(): Invoice
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();
        $invoice = (new InvoiceSum($invoice))->build()->getInvoice();
        $invoice->save();

        return $invoice->service()->markSent()->save();
    }

    private function updatePaymentDate(Payment $payment, string $date, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/' . $payment->hashed_id, array_merge($data, ['date' => $date]));
    }

    private function applicationDate(Paymentable $paymentable): string
    {
        $paymentable = $this->findPaymentable($paymentable->id);

        return Carbon::createFromTimestamp((int) $paymentable->created_at)->toDateString();
    }

    private function findPaymentable(int $paymentable_id): Paymentable
    {
        return Paymentable::withTrashed()
            ->where('id', $paymentable_id)
            ->firstOrFail();
    }

    private function enableFranceReporting(): void
    {
        $settings = $this->company->settings;
        $settings->france_reporting_enabled = true;
        $this->company->settings = $settings;
        $this->company->save();
        $this->company = $this->company->fresh();

        $this->client->group_settings_id = null;
        $this->client->save();
        $this->client = $this->client->fresh();
    }

    public function testUpdatePaymentClientPaidToDate()
    {
        //Create new client
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->assertEquals(0, $client->balance);
        $this->assertEquals(0, $client->paid_to_date);

        //Create Invoice
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();
        $invoice = (new InvoiceSum($invoice))->build()->getInvoice();
        $invoice->save();

        $this->assertEquals(0, $invoice->balance);

        $invoice->service()->markSent()->save();

        $this->assertEquals(10, $invoice->balance);

        $data = [
            'amount' => 10,
            'client_id' => $client->hashed_id,
        ];

        $response = null;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices,paymentables', $data)
        ->assertStatus(200);

        $this->assertEquals(10, $client->fresh()->paid_to_date);
    }
}
