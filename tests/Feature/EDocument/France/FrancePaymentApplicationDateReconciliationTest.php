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
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FrancePaymentReportingMutationGuard;
use App\Services\EDocument\Standards\France\FranceSubmissionClaim;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertSame('2026-10-31', $reports->first()->period->toDateString());
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
            $this->assertArrayHasKey('id', $exception->errors());
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

    public function testSubmittedF10DirectDeleteEndpointsReturnIdValidationAndPreserveState(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $report = $this->reportEvents($invoice)->firstOrFail();
        $report->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $report->save();
        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/payments/' . $payment->hashed_id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');
        $this->withHeaders($headers)
            ->deleteJson('/api/v1/invoices/' . $invoice->hashed_id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');

        $this->assertFalse((bool) $payment->fresh()->is_deleted);
        $this->assertFalse((bool) $invoice->fresh()->is_deleted);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $report->fresh()->payment_status);
        $this->assertCount(1, $this->movementEvents($invoice));
        $this->assertCount(1, $this->reportEvents($invoice));
    }

    public function testSubmittedF10BulkDeleteEndpointsReturnIdValidationAndPreserveState(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $report = $this->reportEvents($invoice)->firstOrFail();
        $report->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $report->save();
        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/payments/bulk?action=delete', ['ids' => [$payment->hashed_id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');
        $this->withHeaders($headers)
            ->postJson('/api/v1/invoices/bulk?action=delete', ['ids' => [$invoice->hashed_id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');

        $this->assertFalse((bool) $payment->fresh()->is_deleted);
        $this->assertFalse((bool) $invoice->fresh()->is_deleted);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $report->fresh()->payment_status);
        $this->assertCount(1, $this->movementEvents($invoice));
        $this->assertCount(1, $this->reportEvents($invoice));
    }

    public function testBulkDeletionAccumulatesSubmittedAndActiveClaimViolations(): void
    {
        [$submitted_invoice, $submitted_payment, $submitted_paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($submitted_invoice, $submitted_payment, $submitted_paymentable);
        $submitted_report = $this->reportEvents($submitted_invoice)->firstOrFail();
        $submitted_report->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $submitted_report->save();
        [$claimed_invoice, $claimed_payment, $claimed_paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-16');
        $this->recordAppliedMovement($claimed_invoice, $claimed_payment, $claimed_paymentable);
        $claimed_report = $this->reportEvents($claimed_invoice)->firstOrFail();
        $claim = app(FranceSubmissionClaim::class);
        $token = $claim->claim([$claimed_report->id]);
        $this->assertNotNull($token);
        $headers = [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];

        try {
            $invoice_response = $this->withHeaders($headers)->postJson('/api/v1/invoices/bulk?action=delete', [
                'ids' => [$submitted_invoice->hashed_id, $claimed_invoice->hashed_id],
            ]);
            $payment_response = $this->withHeaders($headers)->postJson('/api/v1/payments/bulk?action=delete', [
                'ids' => [$submitted_payment->hashed_id, $claimed_payment->hashed_id],
            ]);

            $invoice_response->assertStatus(422)->assertJsonValidationErrors('id');
            $payment_response->assertStatus(422)->assertJsonValidationErrors('id');
            $this->assertSame([
                ctrans('texts.deletion_violation_regulatory'),
            ], $invoice_response->json('errors.id'));
            $this->assertEqualsCanonicalizing([
                'The payment cannot be deleted because its France payment reporting has already been submitted.',
                'The payment cannot be deleted while its France payment reporting is being submitted.',
            ], $payment_response->json('errors.id'));
            $this->assertFalse((bool) $submitted_invoice->fresh()->is_deleted);
            $this->assertFalse((bool) $claimed_invoice->fresh()->is_deleted);
            $this->assertFalse((bool) $submitted_payment->fresh()->is_deleted);
            $this->assertFalse((bool) $claimed_payment->fresh()->is_deleted);
        } finally {
            $claim->release([$claimed_report->id], (string) $token);
        }
    }

    #[DataProvider('nonFrancePeppolConfigurations')]
    public function testMutationGuardsReturnEarlyOutsideFrancePeppol(string $country_code, string $e_invoice_type): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $report = $this->reportEvents($invoice)->firstOrFail();
        $report->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $report->save();
        $claim = app(FranceSubmissionClaim::class);
        $token = $claim->claim([$report->id]);
        $this->assertNotNull($token);
        $country = Country::query()->where('iso_3166_2', $country_code)->firstOrFail();
        $settings = $this->company->settings;
        $settings->country_id = (string) $country->id;
        $settings->e_invoice_type = $e_invoice_type;
        $this->company->settings = $settings;
        $this->company->save();
        $invoice->unsetRelation('company');
        $payment->unsetRelation('company');
        $guard = app(FrancePaymentReportingMutationGuard::class);

        try {
            $this->assertNull($guard->invoiceDeletionViolation($invoice));
            $this->assertNull($guard->paymentDeletionViolation($payment));
            $guard->assertPaymentDateChangeAllowed($payment, '2026-10-01');
            $guard->assertRefundAllowed($payment);
            $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $report->fresh()->payment_status);
        } finally {
            $claim->release([$report->id], (string) $token);
        }
    }

    public function testPendingB2CInvoiceDeletionRemainsAuditableButCannotBeSubmitted(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $report = $this->reportEvents($invoice)->firstOrFail();

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->deleteJson('/api/v1/invoices/' . $invoice->hashed_id)->assertStatus(200);

        $this->assertTrue((bool) $invoice->fresh()->is_deleted);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_PENDING, $report->fresh()->payment_status);
        $this->assertFalse(
            app(FranceEReportCompiler::class)
                ->sourceEvents($this->company, TransactionEvent::FR_REPORT_SUBMISSION_B2C, '2026-09-30')
                ->contains('id', $report->id),
        );
    }

    public function testSubmittedDomesticB2BRefundRecordsOneComplianceExceptionAndPreservesTheAudit(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('business', '2026-09-15');
        $invoice->backup->guid = 'submitted-domestic-b2b-guid';
        $invoice->save();
        $this->recordAppliedMovement($invoice, $payment, $paymentable);

        $notification = $this->notificationEvents($invoice)->firstOrFail();
        $notification->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $notification->save();
        $guard = app(FrancePaymentReportingMutationGuard::class);

        foreach (['payment', 'invoice'] as $mutation) {
            try {
                $mutation === 'payment'
                    ? $guard->assertUserDeletionAllowed($payment)
                    : $guard->assertInvoiceDeletionAllowed($invoice);
                $this->fail("A submitted domestic B2B notification must block {$mutation} deletion.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('id', $exception->errors());
            }
        }

        try {
            $guard->assertPaymentDateChangeAllowed($payment, '2026-10-02');
            $this->fail('A submitted domestic B2B notification must block payment date changes.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('date', $exception->errors());
        }

        $guard->assertRefundAllowed($payment);
        $paymentable->refunded = 200;
        $paymentable->save();
        $payment->refunded = 200;
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->save();
        $invoice->paid_to_date = 1000;
        $invoice->balance = 200;
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->save();
        $reconciler = app(FrancePaymentApplicationDateReconciler::class);
        $payment = $payment->fresh()->load(['client.country', 'client.company', 'company']);
        $invoice = $invoice->fresh()->load(['client.country', 'client.company', 'company']);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $reconciler->reconcilePaymentRemoval($payment, $invoice, $paymentable->id, '2026-10-12');
        }

        $submitted = TransactionEvent::query()->findOrFail($notification->id);
        $exceptions = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_status', TransactionEvent::FR_REPORTING_STATUS_FAILED)
            ->get()
            ->filter(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === 'payment_date_correction_unsupported');
        $notifications = $this->notificationEvents($invoice)
            ->filter(fn(TransactionEvent $event): bool => data_get($event->payment_request, 'fr_kind') === RecordFranceEReportingPayment::KIND_PAYMENT_RECEIVED_NOTIFICATION);

        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_SUBMITTED, $submitted->payment_status);
        $this->assertSame('2026-09-15', data_get($submitted->payment_request, 'source_date'));
        $this->assertCount(1, $notifications);
        $this->assertCount(1, $exceptions);
        $this->assertStringContainsString(
            'Storecove does not expose a reversal or date-correction operation',
            (string) data_get($exceptions->first()->payment_request, 'skip_reason'),
        );
    }

    public function testSubmittedF10CorrectiveCanBeFollowedByAnotherIndependentCorrective(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $this->recordAppliedMovement($invoice, $payment, $paymentable);
        $initial_report = $this->reportEvents($invoice)->firstOrFail();
        $initial_report->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initial_report->save();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 100;
        $payment->save();
        $invoice->paid_to_date = 1100;
        $invoice->balance = 100;
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->save();

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-100',
            '2026-10-12',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'first-refund',
        ))->handle();

        $first_corrective = $this->reportEvents($invoice)->last();
        $first_corrective->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $first_corrective->save();
        $payment->refunded = 150;
        $payment->save();
        $invoice->paid_to_date = 1050;
        $invoice->balance = 150;
        $invoice->save();
        $second_refund = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-50',
            '2026-11-04',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'second-refund',
        );

        $second_refund->handle();
        $second_refund->handle();
        $reports = $this->reportEvents($invoice);
        $second_corrective = $reports->last();

        $this->assertCount(3, $reports);
        $this->assertSame(-50.0, (float) $second_corrective->payment_applied);
        $this->assertSame('corrective', data_get($second_corrective->payment_request, 'fr_report_kind'));
        $this->assertSame((int) $first_corrective->id, (int) data_get($second_corrective->payment_request, 'previous_event_id'));
        $this->assertSame('2026-11-30', $second_corrective->period->toDateString());
        $this->assertCount(1, data_get($second_corrective->payment_request, 'source_event_ids'));
        $this->assertCount(3, $this->movementEvents($invoice));
    }

    public function testOnePaymentAcrossTwoB2CInvoicesKeepsRefundCorrectionsIsolatedPerInvoice(): void
    {
        [$invoice_one, $payment, $paymentable_one] = $this->makePaidPaymentApplication('individual', '2026-09-15');
        $invoice_two = $invoice_one->replicate();
        $invoice_two->number = 'FR-MULTI-INVOICE-SECOND';
        $invoice_two->paid_to_date = $invoice_two->amount;
        $invoice_two->balance = 0;
        $invoice_two->status_id = Invoice::STATUS_PAID;
        $invoice_two->save();
        $payment->amount = 2400;
        $payment->applied = 2400;
        $payment->save();
        $paymentable_two = new Paymentable();
        $paymentable_two->payment_id = $payment->id;
        $paymentable_two->paymentable_id = $invoice_two->id;
        $paymentable_two->paymentable_type = 'invoices';
        $paymentable_two->amount = 1200;
        $paymentable_two->refunded = 0;
        $paymentable_two->created_at = strtotime('2026-09-15 12:00:00');
        $paymentable_two->updated_at = strtotime('2026-09-15 12:00:00');
        $paymentable_two->save();
        $paymentable_two = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $invoice_two->id)
            ->latest('id')
            ->firstOrFail();

        (new RecordFranceEReportingPayment($payment->id, $this->company->db))->handle();
        $initial_one = $this->reportEvents($invoice_one)->firstOrFail();
        $initial_two = $this->reportEvents($invoice_two)->firstOrFail();
        $initial_one->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initial_one->save();
        $initial_two->payment_status = TransactionEvent::FR_REPORTING_STATUS_SUBMITTED;
        $initial_two->save();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 300;
        $payment->save();
        Paymentable::withTrashed()->where('id', $paymentable_one->id)->update(['refunded' => 100]);
        Paymentable::withTrashed()->where('id', $paymentable_two->id)->update(['refunded' => 200]);

        foreach ([
            [$invoice_one, $paymentable_one, -100, 1100, 'first-invoice-refund'],
            [$invoice_two, $paymentable_two, -200, 1000, 'second-invoice-refund'],
        ] as [$invoice, $paymentable, $amount, $paid_to_date, $mutation_key]) {
            $invoice->paid_to_date = $paid_to_date;
            $invoice->balance = 1200 - $paid_to_date;
            $invoice->status_id = Invoice::STATUS_PARTIAL;
            $invoice->save();
            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                (string) $amount,
                '2026-10-12',
                FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
                $mutation_key,
            ))->handle();
        }

        $corrective_one = $this->reportEvents($invoice_one)->last();
        $corrective_two = $this->reportEvents($invoice_two)->last();

        $this->assertSame(-100.0, (float) $corrective_one->payment_applied);
        $this->assertSame(-200.0, (float) $corrective_two->payment_applied);
        $this->assertSame((int) $initial_one->id, (int) data_get($corrective_one->payment_request, 'previous_event_id'));
        $this->assertSame((int) $initial_two->id, (int) data_get($corrective_two->payment_request, 'previous_event_id'));
        $this->assertSame($paymentable_one->id, data_get($this->movementEvents($invoice_one)->last()->payment_request, 'paymentable_id'));
        $this->assertSame($paymentable_two->id, data_get($this->movementEvents($invoice_two)->last()->payment_request, 'paymentable_id'));
        $this->assertCount(2, $this->reportEvents($invoice_one));
        $this->assertCount(2, $this->reportEvents($invoice_two));
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
        $settings->e_invoice_type = 'PEPPOL';
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

    /** @return array<string, array{0: string, 1: string}> */
    public static function nonFrancePeppolConfigurations(): array
    {
        return [
            'non-France PEPPOL' => ['US', 'PEPPOL'],
            'France non-PEPPOL' => ['FR', 'EN16931'],
            'non-France non-PEPPOL' => ['US', 'EN16931'],
        ];
    }
}
