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

namespace Tests\Feature\EDocument\France;

use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Events\Payment\PaymentApplicationDateChanged;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Listeners\Payment\ReconcilePaymentApplicationDateChange;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\Product;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceRuntimeProjection;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\MockAccountData;
use Tests\TestCase;

class FrancePaymentApplicationDateReconciliationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->enableFranceReporting();
    }

    public function test_date_change_listener_does_not_duplicate_transaction_coupled_france_facts(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('2026-08-31');
        $this->assertTrue($invoice->client->reportableFrTransaction());
        $cashReconciler = Mockery::mock(InvoiceTransactionEventEntryCash::class);
        $cashReconciler->shouldReceive('reconcileApplicationDateChange')
            ->once()
            ->with($invoice->id, $payment->id, '2026-08-31', '2026-09-01', [$paymentable->id]);
        Queue::fake([RecordFranceEReportingPayment::class]);

        (new ReconcilePaymentApplicationDateChange($cashReconciler))->handle(
            new PaymentApplicationDateChanged(
                payment_id: $payment->id,
                db: $this->company->db,
                old_date: '2026-08-31',
                new_date: '2026-09-01',
                paymentable_ids: [$paymentable->id],
            ),
        );

        Queue::assertNotPushed(RecordFranceEReportingPayment::class);
    }

    public function test_partially_refunded_date_change_moves_only_the_net_application(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('2026-08-31');
        Paymentable::query()->where('id', $paymentable->id)->update(['refunded' => 40]);
        $paymentable->refunded = 40;
        TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->delete();

        app(FrancePaymentApplicationRecorder::class)->recordApplicationDateChange(
            $payment,
            $paymentable,
            '2026-08-31',
            '2026-09-01',
            'partial-refund-date-change',
        );

        $this->assertSame(
            ['-80.00', '80.00'],
            $this->movements($payment)->map(
                fn(TransactionEvent $event): string => (string) data_get($event->payment_request, 'movement_amount'),
            )->all(),
        );
    }

    public function test_repeated_date_cycles_remain_distinct_while_transaction_retries_deduplicate(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('2026-08-31');
        TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->delete();
        $recorder = app(FrancePaymentApplicationRecorder::class);

        $recorder->recordMovement(
            payment: $payment,
            invoice: $invoice,
            paymentable: $paymentable,
            movementAmount: '120',
            movementDate: '2026-08-31',
            movementIdentity: 'initial-application',
        );
        $recorder->recordApplicationDateChange($payment, $paymentable, '2026-08-31', '2026-09-01', 'cycle-1');
        $recorder->recordApplicationDateChange($payment, $paymentable, '2026-08-31', '2026-09-01', 'cycle-1');
        $recorder->recordApplicationDateChange($payment, $paymentable, '2026-09-01', '2026-08-31', 'cycle-2');
        $recorder->recordApplicationDateChange($payment, $paymentable, '2026-08-31', '2026-09-01', 'cycle-3');

        $events = $this->movements($payment);

        $this->assertCount(7, $events);
        $this->assertSame([
            '2026-08-31' => '0.00',
            '2026-09-30' => '120.00',
        ], $events->groupBy(fn(TransactionEvent $event): string => $event->period->toDateString())
            ->map(fn($periodEvents): string => $periodEvents->reduce(
                fn(string $total, TransactionEvent $event): string => bcadd(
                    $total,
                    (string) data_get($event->payment_request, 'movement_amount'),
                    2,
                ),
                '0.00',
            ))
            ->all());
    }

    public function test_same_month_date_change_projects_only_the_surviving_application_date(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('2026-09-01');
        TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->delete();
        $recorder = app(FrancePaymentApplicationRecorder::class);
        $recorder->recordMovement(
            payment: $payment,
            invoice: $invoice,
            paymentable: $paymentable,
            movementAmount: '120',
            movementDate: '2026-09-01',
            movementIdentity: 'same-month-initial',
        );
        $recorder->recordApplicationDateChange(
            $payment,
            $paymentable,
            '2026-09-01',
            '2026-09-20',
            'same-month-date-change',
        );
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-20', 'Europe/Paris'),
        );

        $subjects = app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
        );

        $this->assertCount(1, $subjects);
        $this->assertSame('2026-09-20', $subjects[0]->entry->b2cPayment?->date);
    }

    public function test_date_change_facts_are_immutable_idempotent_and_move_runtime_periods(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('2026-08-31');
        $original = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-08-31',
        );
        $reverse = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-120',
            '2026-08-31',
            FrancePaymentApplicationRecorder::MOVEMENT_DATE_REVERSED,
            'date-change:reverse',
        );
        $reapply = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-01',
            FrancePaymentApplicationRecorder::MOVEMENT_DATE_REAPPLIED,
            'date-change:reapply',
        );
        $secondReverse = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-120',
            '2026-09-01',
            FrancePaymentApplicationRecorder::MOVEMENT_DATE_REVERSED,
            'second-date-change:reverse',
        );
        $secondReapply = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-10-01',
            FrancePaymentApplicationRecorder::MOVEMENT_DATE_REAPPLIED,
            'second-date-change:reapply',
        );

        $original->handle();
        $source = $this->movements($payment)->firstOrFail();
        $reverse->handle();
        $reverse->handle();
        $reapply->handle();
        $reapply->handle();
        $secondReverse->handle();
        $secondReapply->handle();

        $events = $this->movements($payment);

        $this->assertCount(5, $events);
        $this->assertSame($source->payment_request, $source->fresh()->payment_request);
        $this->assertSame(
            ['applied', 'application_date_reversed', 'application_date_reapplied', 'application_date_reversed', 'application_date_reapplied'],
            $events->map(fn (TransactionEvent $event): string => (string) data_get($event->payment_request, 'movement_type'))->all(),
        );
        $this->assertSame(
            ['120.00', '-120.00', '120.00', '-120.00', '120.00'],
            $events->map(fn (TransactionEvent $event): string => (string) data_get($event->payment_request, 'movement_amount'))->all(),
        );
        $this->assertSame(
            ['2026-08-31', '2026-08-31', '2026-09-30', '2026-09-30', '2026-10-31'],
            $events->pluck('period')->map->toDateString()->all(),
        );
        $this->assertTrue($events->every(fn (TransactionEvent $event): bool => is_null($event->reporting_data)));
    }

    /** @return array{0: Invoice, 1: Payment, 2: Paymentable} */
    private function paymentScenario(string $date): array
    {
        $this->client->classification = 'individual';
        $this->client->group_settings_id = null;
        $this->client->save();
        $lineItems = $this->invoice->line_items;

        foreach ($lineItems as $lineItem) {
            $lineItem->type_id = (string) Product::PRODUCT_TYPE_SERVICE;
        }

        $this->invoice->line_items = $lineItems;
        $this->invoice->status_id = Invoice::STATUS_PAID;
        $this->invoice->paid_to_date = 120;
        $this->invoice->balance = 0;
        $this->invoice->save();
        $payment = Payment::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 120,
            'applied' => 120,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => $date,
            'currency_id' => 3,
        ]);
        $paymentable = new Paymentable();
        $paymentable->payment_id = $payment->id;
        $paymentable->paymentable_id = $this->invoice->id;
        $paymentable->paymentable_type = 'invoices';
        $paymentable->amount = 120;
        $paymentable->refunded = 0;
        $paymentable->created_at = strtotime($date);
        $paymentable->updated_at = strtotime($date);
        $paymentable->save();
        $paymentable = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $this->invoice->id)
            ->where('paymentable_type', 'invoices')
            ->latest('id')
            ->firstOrFail();

        return [$this->invoice->fresh(), $payment, $paymentable];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, TransactionEvent> */
    private function movements(Payment $payment): \Illuminate\Database\Eloquent\Collection
    {
        return TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('payment_id', $payment->id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->orderBy('id')
            ->get();
    }

    private function enableFranceReporting(): void
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $settings = $this->company->settings ?: CompanySettings::defaults();
        $settings->country_id = (string) $france->id;
        $settings->france_reporting_enabled = true;
        $settings->currency_id = '3';
        $settings->vat_number = 'FR52552100554';
        $settings->id_number = '552100554';
        $taxData = new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = 'FR';
        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->save();
        $this->company = $this->company->fresh();
    }
}
