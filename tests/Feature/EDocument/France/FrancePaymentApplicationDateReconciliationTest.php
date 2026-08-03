<?php

namespace Tests\Feature\EDocument\France;

use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Factory\InvoiceItemFactory;
use App\Jobs\Cron\FranceEReportingCron;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Jobs\EDocument\SubmitFranceEReport;
use App\Jobs\EDocument\SubmitFrancePaymentReceivedNotification;
use App\Models\Client;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\Product;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateReconciler;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationDateResolver;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FrancePaymentReportingMutationGuard;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
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

    public function testPendingF10MovementAndReportAreRebuiltWithoutDuplicates(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);

        $movement = $this->movementEvents($invoice)->firstOrFail();
        $report = $this->reportEvents($invoice)->firstOrFail();
        $originalReportId = $report->id;

        $this->movePaymentableDate($paymentable, '2026-10-02');
        $reconciler = app(FrancePaymentApplicationDateReconciler::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $reconciler->reconcile(
                $invoice->id,
                $payment->id,
                '2026-09-15',
                '2026-10-02',
                [$paymentable->id],
            );
        }

        $movements = $this->movementEvents($invoice);
        $reports = $this->reportEvents($invoice);

        $this->assertCount(1, $movements);
        $this->assertCount(1, $reports);
        $this->assertSame($movement->id, $movements->first()->id);
        $this->assertSame($originalReportId, $reports->first()->id);
        $this->assertSame('2026-10-02', data_get($movements->first()->payment_request, 'source_date'));
        $this->assertSame('2026-10-02', data_get($reports->first()->payment_request, 'source_date'));
        $this->assertSame('2026-10-10', $reports->first()->period->toDateString());
        $this->assertSame('2026-10-02', $reports->first()->reporting_data->frReportEntry->b2cPayment->date);
    }

    public function testDateReconciliationDoesNotRetainAnInitialReportWhileTheInvoiceIsNotPaidInFull(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $invoice->paid_to_date = 1000;
        $invoice->balance = 200;
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->save();
        $this->movePaymentableDate($paymentable, '2026-09-18');

        app(FrancePaymentApplicationDateReconciler::class)->reconcile(
            $invoice->id,
            $payment->id,
            '2026-09-15',
            '2026-09-18',
            [$paymentable->id],
        );

        $movement = $this->movementEvents($invoice)->firstOrFail();

        $this->assertCount(0, $this->reportEvents($invoice));
        $this->assertNull(data_get($movement->payment_request, 'report_event_id'));

        $invoice->paid_to_date = 1200;
        $invoice->balance = 0;
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->save();

        app(FrancePaymentApplicationDateReconciler::class)->reconcile(
            $invoice->id,
            $payment->id,
            '2026-09-18',
            '2026-09-18',
            [$paymentable->id],
        );

        $report = $this->reportEvents($invoice)->firstOrFail();

        $this->assertSame(1200.0, (float) $report->payment_applied);
        $this->assertSame('2026-09-18', data_get($report->payment_request, 'source_date'));
    }

    public function testSubmittedF10ReportingIsImmutableAndUserMutationsAreBlocked(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);

        $report = $this->reportEvents($invoice)->firstOrFail();
        $report->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $report->save();
        $guard = app(FrancePaymentReportingMutationGuard::class);

        try {
            $guard->assertPaymentDateChangeAllowed($payment, '2026-10-02');
            $this->fail('A submitted France payment report must block payment date changes.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('date', $exception->errors());
        }

        try {
            $guard->assertUserDeletionAllowed($payment);
            $this->fail('A submitted France payment report must block payment deletion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }

        $this->movePaymentableDate($paymentable, '2026-10-02');
        $reconciler = app(FrancePaymentApplicationDateReconciler::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $reconciler->reconcile(
                $invoice->id,
                $payment->id,
                '2026-09-15',
                '2026-10-02',
                [$paymentable->id],
            );
        }

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $report->fresh()->payment_status);
        $this->assertSame('2026-09-15', data_get($report->fresh()->payment_request, 'source_date'));
        $exceptions = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_FAILED)
            ->get()
            ->filter(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === 'payment_date_correction_unsupported');

        $this->assertCount(1, $exceptions);
    }

    public function testDeletedMovementRetainsItsPaymentableIdentityAfterThePivotIsForceDeleted(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $paymentableId = $paymentable->id;

        $paymentable->forceDelete();
        $payment->status_id = Payment::STATUS_CANCELLED;
        $payment->save();
        $invoice->paid_to_date = 0;
        $invoice->balance = $invoice->amount;
        $invoice->status_id = Invoice::STATUS_SENT;
        $invoice->save();

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentableId,
            '-1200',
            '2026-09-18',
            FrancePaymentApplicationRecorder::MOVEMENT_DELETED,
        ))->handle();

        $movements = $this->movementEvents($invoice);
        $deleted = $movements->first(
            fn(TransactionEvent $event): bool => data_get($event->payment_request, 'movement_type') === FrancePaymentApplicationRecorder::MOVEMENT_DELETED,
        );

        $this->assertCount(2, $movements);
        $this->assertNotNull($deleted);
        $this->assertSame($paymentableId, (int) data_get($deleted->payment_request, 'paymentable_id'));
        $this->assertCount(0, $this->reportEvents($invoice));
    }

    public function testPendingDomesticB2BNotificationIsRebuiltInPlace(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('business', '2026-09-15');
        $invoice->backup->guid = 'original-storecove-guid';
        $invoice->save();
        $this->recordAppliedMovement($invoice, $payment, $paymentable);

        $notification = $this->notificationEvents($invoice)->firstOrFail();
        $idempotencyGuid = data_get($notification->payment_request, 'idempotency_guid');
        $this->movePaymentableDate($paymentable, '2026-09-18');

        app(FrancePaymentApplicationDateReconciler::class)->reconcile(
            $invoice->id,
            $payment->id,
            '2026-09-15',
            '2026-09-18',
            [$paymentable->id],
        );

        $notifications = $this->notificationEvents($invoice);

        $this->assertCount(1, $notifications);
        $this->assertSame($notification->id, $notifications->first()->id);
        $this->assertSame('2026-09-18', data_get($notifications->first()->payment_request, 'source_date'));
        $this->assertSame($idempotencyGuid, data_get($notifications->first()->payment_request, 'idempotency_guid'));
    }

    public function testLatestCompletedApplicationUsesCreatedAtThenIdAndExcludesInactiveRows(): void
    {
        [$invoice, $first_payment, $first_paymentable] = $this->makePaidPaymentApplication('business', '2026-09-15');
        $second_payment = Payment::factory()->create([
            'client_id' => $first_payment->client_id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => '800',
            'applied' => '800',
            'refunded' => '100',
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => '2026-10-30',
            'currency_id' => 3,
        ]);
        $second_paymentable = new Paymentable();
        $second_paymentable->payment_id = $second_payment->id;
        $second_paymentable->paymentable_id = $invoice->id;
        $second_paymentable->paymentable_type = 'invoices';
        $second_paymentable->amount = '800';
        $second_paymentable->refunded = 0;
        $second_paymentable->created_at = strtotime('2026-09-15 12:00:00');
        $second_paymentable->updated_at = strtotime('2026-09-15 12:00:00');
        $second_paymentable->save();
        $second_paymentable = Paymentable::withTrashed()
            ->where('payment_id', $second_payment->id)
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice->id)
            ->latest('id')
            ->firstOrFail();
        $resolver = app(FrancePaymentApplicationDateResolver::class);

        $this->assertSame(Payment::STATUS_COMPLETED, (int) $second_payment->fresh()->status_id);
        $this->assertFalse($second_payment->fresh()->is_deleted);
        $this->assertFalse(Paymentable::query()->findOrFail($second_paymentable->id)->trashed());

        $this->assertSame($second_paymentable->id, $resolver->latestCompletedInvoiceApplication($invoice->id)?->id);

        $invoice->backup->guid = 'canonical-storecove-guid';
        $invoice->save();
        $this->recordAppliedMovement($invoice, $first_payment, $first_paymentable);
        $first_payment->amount = 350;
        $first_payment->refunded = 50;
        $first_payment->save();

        app(FrancePaymentApplicationDateReconciler::class)->reconcile(
            $invoice->id,
            $first_payment->id,
            '2026-09-15',
            '2026-09-15',
            [$first_paymentable->id],
        );

        $notification = $this->notificationEvents($invoice)->firstOrFail();

        $this->assertSame($second_payment->id, $notification->payment_id);
        $this->assertSame($second_paymentable->id, (int) data_get($notification->payment_request, 'paymentable_id'));
        $this->assertSame(800.0, (float) $notification->payment_amount);
        $this->assertSame(100.0, (float) $notification->payment_refunded);
        $this->assertSame('2026-09-15', data_get($notification->payment_request, 'source_date'));

        $this->movePaymentableDate($first_paymentable, '2026-09-20');

        $this->assertSame($first_paymentable->id, $resolver->latestCompletedInvoiceApplication($invoice->id)?->id);

        $first_payment->status_id = Payment::STATUS_CANCELLED;
        $first_payment->save();

        $this->assertSame($second_paymentable->id, $resolver->latestCompletedInvoiceApplication($invoice->id)?->id);

        $second_paymentable->delete();

        $this->assertNull($resolver->latestCompletedInvoiceApplication($invoice->id));
    }

    public function testReconciliationRemovesPendingNotificationWhenNoActiveApplicationRemains(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('business', '2026-09-15');
        $invoice->backup->guid = 'original-storecove-guid';
        $invoice->save();
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $this->assertCount(1, $this->notificationEvents($invoice));

        $payment->status_id = Payment::STATUS_CANCELLED;
        $payment->save();
        $paymentable->delete();

        app(FrancePaymentApplicationDateReconciler::class)->reconcile(
            $invoice->id,
            $payment->id,
            '2026-09-15',
            '2026-09-18',
            [$paymentable->id],
        );

        $this->assertCount(0, $this->notificationEvents($invoice));
    }

    public function testFutureDomesticB2BNotificationRemainsPendingUntilItsApplicationDate(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('business', '2026-09-18');
        $invoice->backup->guid = 'future-storecove-guid';
        $invoice->save();
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $notification = $this->notificationEvents($invoice)->firstOrFail();

        $this->travelTo(CarbonImmutable::parse('2026-09-17 09:00:00', 'Europe/Paris'));

        try {
            (new SubmitFrancePaymentReceivedNotification(
                $notification->id,
                $this->company->db,
            ))->handle(new Storecove());

            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $notification->fresh()->payment_status);
        } finally {
            $this->travelBack();
        }
    }

    public function testHistoricalCorrectiveF10ReportRemainsDispatchable(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-08 22:00:00', 'Europe/Paris'));

        try {
            config(['ninja.db.multi_db_enabled' => false]);
            Bus::fake([SubmitFranceEReport::class]);
            [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
            $this->recordAppliedMovement($invoice, $payment, $paymentable);
            $report = $this->reportEvents($invoice)->firstOrFail();
            $request = $report->payment_request;
            $request['fr_report_kind'] = RecordFranceEReportingPayment::REPORT_KIND_CORRECTIVE;
            $report->payment_request = $request;
            $report->period = '2026-09-20';
            $report->save();
            $futureReport = $report->replicate();
            $futureReport->period = '2026-10-20';
            $futureReport->save();

            (new FranceEReportingCron())->handle();

            Bus::assertDispatchedTimes(SubmitFranceEReport::class, 1);
            Bus::assertDispatched(SubmitFranceEReport::class, function (SubmitFranceEReport $job): bool {
                $period = new \ReflectionProperty($job, 'periodEnd');

                return $period->getValue($job) === '2026-09-20';
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array{0: Invoice, 1: Payment, 2: Paymentable}
     */
    private function makePaidPaymentApplication(string $classification, string $applicationDate): array
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $france->id,
            'classification' => $classification,
            'group_settings_id' => null,
            'name' => 'France payment date client',
            'postal_code' => '75001',
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 1000;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 20;
        $item->tax_id = (string) Product::PRODUCT_TYPE_OVERRIDE_TAX;
        $item->type_id = (string) Product::PRODUCT_TYPE_SERVICE;

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => '2026-09-01',
            'due_date' => '2026-10-01',
            'status_id' => Invoice::STATUS_SENT,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'line_items' => [$item],
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->paid_to_date = $invoice->amount;
        $invoice->balance = 0;
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->save();

        $payment = Payment::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => '1200',
            'applied' => '1200',
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => $applicationDate,
            'currency_id' => 3,
        ]);

        $paymentable = new Paymentable();
        $paymentable->payment_id = $payment->id;
        $paymentable->paymentable_id = $invoice->id;
        $paymentable->paymentable_type = 'invoices';
        $paymentable->amount = '1200';
        $paymentable->refunded = 0;
        $paymentable->created_at = strtotime($applicationDate . ' 12:00:00');
        $paymentable->updated_at = strtotime($applicationDate . ' 12:00:00');
        $paymentable->save();

        return [
            $invoice->fresh(),
            $payment->fresh(),
            Paymentable::withTrashed()
                ->where('payment_id', $payment->id)
                ->where('paymentable_type', 'invoices')
                ->where('paymentable_id', $invoice->id)
                ->latest('id')
                ->firstOrFail(),
        ];
    }

    private function recordAppliedMovement(Invoice $invoice, Payment $payment, Paymentable $paymentable): void
    {
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            (string) $paymentable->amount,
            date('Y-m-d', (int) $paymentable->created_at),
        ))->handle();
    }

    private function movePaymentableDate(Paymentable $paymentable, string $date): void
    {
        \DB::table('paymentables')->where('id', $paymentable->id)->update([
            'created_at' => $date . ' 12:00:00',
            'updated_at' => $date . ' 12:00:00',
        ]);
    }

    /** @return EloquentCollection<int, TransactionEvent> */
    private function movementEvents(Invoice $invoice): EloquentCollection
    {
        return TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2C_PAYMENT)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_DEFERRED)
            ->orderBy('id')
            ->get();
    }

    /** @return EloquentCollection<int, TransactionEvent> */
    private function reportEvents(Invoice $invoice): EloquentCollection
    {
        return TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2C_PAYMENT)
            ->whereIn('payment_status', [
                TransactionEvent::FR_REPORTING_STATUS_PENDING,
                TransactionEvent::FR_REPORTING_STATUS_SUBMITTED,
            ])
            ->orderBy('id')
            ->get();
    }

    /** @return EloquentCollection<int, TransactionEvent> */
    private function notificationEvents(Invoice $invoice): EloquentCollection
    {
        return TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->orderBy('id')
            ->get();
    }

    private function enableFranceReporting(): void
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $settings = $this->company->settings ?: CompanySettings::defaults();
        $settings->country_id = (string) $france->id;
        $settings->france_reporting_enabled = true;
        $settings->france_reporting_schedule = 'ten_day';
        $settings->currency_id = '3';
        $settings->vat_number = 'FR12345678901';
        $settings->id_number = '12345678900012';

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
