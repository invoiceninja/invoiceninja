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

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Factory\InvoiceItemFactory;
use App\Jobs\Ninja\CheckACHStatus;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Jobs\EDocument\RecordFranceEReportingDocumentLifecycle;
use App\Jobs\EDocument\RecordFranceEReportingScopeInvalidation;
use App\Jobs\Cron\FranceEReportingCron;
use App\Jobs\EDocument\SubmitFrancePaymentReceivedNotification;
use App\Jobs\EDocument\UpdateFranceEReportSubmissionStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\Product;
use App\Models\TransactionEvent;
use App\Models\Webhook;
use App\Observers\PaymentObserver;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FrancePaymentNotificationProcessor;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\FranceRuntimeProjection;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\MockAccountData;
use Tests\TestCase;

class FranceEReportingPaymentMovementTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('FRREPORTING::');
        
        $this->makeTestData();
        $this->enableFranceReporting();
    }

    public function test_payment_capture_is_an_idempotent_immutable_fact(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $job = new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        );

        $job->handle();
        $job->handle();

        $events = $this->movements($payment);
        $this->assertCount(1, $events);
        $this->assertNull($events->first()->reporting_data);
        $this->assertNull($events->first()->payment_status);
        $this->assertSame(0, $events->first()->credit_id);
        $this->assertSame('fact', data_get($events->first()->payment_request, 'role'));
        $this->assertSame('f10', data_get($events->first()->payment_request, 'reporting_path'));
        $this->assertSame('120.00', data_get($events->first()->payment_request, 'movement_amount'));
    }

    public function test_direct_capture_does_not_duplicate_a_source_revision_already_reconciled(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');

        (new RecordFranceEReportingScopeInvalidation(
            companyId: $this->company->id,
            db: $this->company->db,
            invalidationKey: 'concurrent-source-reconciliation',
            reconcileRecentSourceState: true,
            sourceReconciliationSince: '2026-09-24T00:00:00+00:00',
        ))->handle();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
            FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
            'direct-writer-after-reconciliation',
        ))->handle();

        $this->assertCount(1, $this->movements($payment));
        $this->assertSame('120.00', data_get(
            $this->movements($payment)->firstOrFail()->payment_request,
            'movement_amount',
        ));
    }

    public function test_source_reconciliation_reloads_payment_state_after_acquiring_the_company_lock(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $stalePaymentable = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->firstOrFail();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $stalePaymentable->id,
            '120',
            '2026-09-25',
        ))->handle();

        Paymentable::query()->where('id', $stalePaymentable->id)->getQuery()->update([
            'refunded' => 20,
            'updated_at' => '2026-09-26 00:00:00',
        ]);
        Payment::query()->whereKey($payment->id)->getQuery()->update([
            'refunded' => 20,
            'status_id' => Payment::STATUS_PARTIALLY_REFUNDED,
            'updated_at' => '2026-09-26 00:00:00',
        ]);
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $stalePaymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'direct-refund-before-reconciliation-lock',
        ))->handle();

        $method = new \ReflectionMethod(RecordFranceEReportingScopeInvalidation::class, 'initializePaymentableFacts');
        $method->invoke(new RecordFranceEReportingScopeInvalidation(
            companyId: $this->company->id,
            db: $this->company->db,
            invalidationKey: 'stale-source-reconciliation',
            reconcileRecentSourceState: true,
            sourceReconciliationSince: '2026-09-24T00:00:00+00:00',
        ), $stalePaymentable);

        $this->assertSame(
            ['120.00', '-20.00'],
            $this->movements($payment)->map(
                fn(TransactionEvent $event): string => data_get($event->payment_request, 'movement_amount'),
            )->all(),
        );
    }

    public function test_later_refund_targets_the_original_payment_reporting_period(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();

        $payment->status_id = Payment::STATUS_REFUNDED;
        $payment->refunded = 120;
        $payment->save();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-120',
            '2026-10-15',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refund:120',
        ))->handle();

        $events = $this->movements($payment);
        $this->assertCount(2, $events);
        $this->assertSame(['120.00', '-120.00'], $events->map(
            fn(TransactionEvent $event): string => (string) data_get($event->payment_request, 'movement_amount'),
        )->all());
        $this->assertSame(['2026-09-30', '2026-09-30'], $events->pluck('period')->map->toDateString()->all());
        $this->assertSame(['2026-09-25', '2026-09-25'], $events->map(
            fn(TransactionEvent $event): string => (string) data_get($event->payment_request, 'report_date'),
        )->all());
        $this->assertSame('2026-10-15', data_get($events->last()->payment_request, 'effective_at'));
    }

    public function test_failed_settled_payment_creates_a_correction_against_the_accepted_period(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25', 'Europe/Paris'),
        );
        $materializer = app(FranceReportMaterializer::class);
        $initial = $materializer->materialize(
            $this->company->fresh(),
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-07 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($initial);
        $initial->payment_status = FranceReportingStatus::Accepted->value;
        $initial->save();
        TransactionEvent::query()
            ->whereIn('id', data_get($initial->payment_request, 'snapshot_event_ids', []))
            ->update(['payment_status' => FranceReportingStatus::Accepted->value]);
        $materializer->resolveSubmissionFacts($initial, FranceReportingStatus::Accepted);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'Europe/Paris'));

        $payment->status_id = Payment::STATUS_FAILED;
        $payment->save();
        $this->reconcileSourceState('2026-10-14T00:00:00+00:00');

        $this->assertSame(['120.00', '-120.00'], $this->movements($payment)->map(
            fn(TransactionEvent $event): string => data_get($event->payment_request, 'movement_amount'),
        )->all());
        $corrective = $materializer->materialize(
            $this->company->fresh(),
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-15 12:05:00', 'Europe/Paris'),
        );
        $this->assertNotNull($corrective);
        $this->assertSame('payment_re', data_get($corrective->payment_request, 'variant'));
    }

    public function test_quiet_async_completion_then_failure_is_preserved_between_reconciliations(): void
    {
        [$invoice, $payment] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $observer = app(PaymentObserver::class);
        TransactionEvent::query()
            ->where('payment_id', $payment->id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->delete();

        Payment::query()->whereKey($payment->id)->update(['status_id' => Payment::STATUS_PENDING]);
        $payment = $payment->fresh();
        $completePayment = new \ReflectionMethod(CheckACHStatus::class, 'completePayment');
        $completePayment->invoke(new CheckACHStatus(), $payment);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'Europe/Paris'));
        $payment = $payment->fresh();
        Payment::query()->whereKey($payment->id)->update(['status_id' => Payment::STATUS_FAILED]);
        $payment->status_id = Payment::STATUS_FAILED;
        $observer->updated($payment);

        $this->assertSame(['120.00', '-120.00'], $this->movements($payment)->map(
            fn(TransactionEvent $event): string => data_get($event->payment_request, 'movement_amount'),
        )->all());
        $this->assertSame($invoice->id, $this->movements($payment)->firstOrFail()->invoice_id);
    }

    public function test_payment_observer_isolates_france_fact_failures_after_commit(): void
    {
        [, $payment] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $recorder = \Mockery::mock(FrancePaymentApplicationRecorder::class);
        $recorder->shouldReceive('recordStatusTransition')
            ->once()
            ->andThrow(new \RuntimeException('simulated France fact failure'));
        app()->instance(FrancePaymentApplicationRecorder::class, $recorder);

        app(PaymentObserver::class)->updated($payment);

        $this->assertTrue(true);
    }

    public function test_payment_observer_does_not_resolve_the_recorder_when_reporting_is_disabled(): void
    {
        [, $payment] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $this->disableFranceReporting();
        $payment->unsetRelation('company');
        $recorder = \Mockery::mock(FrancePaymentApplicationRecorder::class);
        $recorder->shouldNotReceive('recordStatusTransition');
        app()->instance(FrancePaymentApplicationRecorder::class, $recorder);

        app(PaymentObserver::class)->updated($payment);

        $this->assertFalse((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_ach_completion_does_not_resolve_the_recorder_when_reporting_is_disabled(): void
    {
        [, $payment] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        Payment::query()->whereKey($payment->id)->update(['status_id' => Payment::STATUS_PENDING]);
        $payment = $payment->fresh();
        $this->disableFranceReporting();
        $payment->unsetRelation('company');
        $recorder = \Mockery::mock(FrancePaymentApplicationRecorder::class);
        $recorder->shouldNotReceive('recordStatusTransition');
        app()->instance(FrancePaymentApplicationRecorder::class, $recorder);
        $completePayment = new \ReflectionMethod(CheckACHStatus::class, 'completePayment');

        $completePayment->invoke(new CheckACHStatus(), $payment);

        $this->assertSame(Payment::STATUS_COMPLETED, (int) $payment->fresh()->status_id);
    }

    public function test_domestic_business_payment_is_routed_to_payment_notification_facts(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();

        $this->assertSame(
            'payment_received_notification',
            data_get($this->movements($payment)->first()?->payment_request, 'reporting_path'),
        );
    }

    public function test_company_cron_processes_one_notification_per_invoice_and_document_guid(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'cron-grouped-document-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = now()->toIso8601String();
        $invoice->backup = $backup;
        $invoice->saveQuietly();

        foreach (['first', 'second'] as $identity) {
            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                '60',
                '2026-09-25',
                FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
                $identity,
                'payment_received_notification',
                'cron-grouped-document-guid',
            ))->handle();
        }
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '60',
            '2026-09-27',
            FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
            'future',
            'payment_received_notification',
            'cron-grouped-document-guid',
        ))->handle();
        $futureMovement = $this->movements($payment)->last();

        [$secondInvoice, $secondPayment, $secondPaymentable] = $this->paymentScenario(
            'FR',
            'business',
            '2026-09-25',
        );
        $secondBackup = $secondInvoice->backup;
        $secondBackup->guid = 'cron-second-document-guid';
        $secondBackup->e_invoice_status = 'cleared';
        $secondBackup->e_invoice_cleared_at = now()->toIso8601String();
        $secondInvoice->backup = $secondBackup;
        $secondInvoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $secondPayment->id,
            $this->company->db,
            $secondInvoice->id,
            $secondPaymentable->id,
            '120',
            '2026-09-25',
            FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
            'second-invoice',
            'payment_received_notification',
            'cron-second-document-guid',
        ))->handle();

        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Bus::fake([SubmitFrancePaymentReceivedNotification::class]);
        $notificationNumber = 0;
        Http::fake(static function () use (&$notificationNumber) {
            $notificationNumber++;

            return Http::response([
                'guid' => 'cron-grouped-notification-guid-' . $notificationNumber,
            ], 200);
        });
        $method = new \ReflectionMethod(FranceEReportingCron::class, 'processPaymentNotifications');

        $method->invoke(
            new FranceEReportingCron(),
            $this->company,
            $this->company->db,
            CarbonImmutable::now('Europe/Paris'),
            app(FrancePaymentNotificationProcessor::class),
        );

        $submissions = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->whereIn('invoice_id', [$invoice->id, $secondInvoice->id])
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $submissions);
        $this->assertCount(2, data_get(
            $submissions->firstOrFail()->payment_request,
            'movement_event_ids',
        ));
        $this->assertCount(1, data_get(
            $submissions->last()->payment_request,
            'movement_event_ids',
        ));
        $this->assertSame(
            [FranceReportingStatus::Sent->value],
            $submissions->pluck('payment_status')->unique()->values()->all(),
        );
        $this->assertNull($futureMovement->fresh()->payment_status);
        Bus::assertNotDispatched(SubmitFrancePaymentReceivedNotification::class);
        Http::assertSentCount(2);
    }

    public function test_domestic_payment_waits_until_the_invoice_is_paid_in_full(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->saveQuietly();
        $backup = $invoice->backup;
        $backup->guid = 'full-payment-required-document-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '100',
            '2026-09-25',
            movementIdentity: 'partial-application',
        ))->handle();
        $partialMovement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'full-payment-notification-guid'], 200));

        (new SubmitFrancePaymentReceivedNotification(
            $partialMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertFalse(TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->exists());
        Http::assertNothingSent();

        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '20',
            '2026-09-26',
            movementIdentity: 'final-application',
        ))->handle();
        $finalMovement = $this->movements($payment)->last();
        $this->assertInstanceOf(TransactionEvent::class, $finalMovement);

        (new SubmitFrancePaymentReceivedNotification(
            $finalMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $this->assertSame(FranceReportingStatus::Sent->value, $submission->payment_status);
        $this->assertCount(2, data_get($submission->payment_request, 'movement_event_ids'));
        Http::assertSentCount(1);
    }

    public function test_cross_period_instalments_are_aggregated_when_the_invoice_becomes_paid(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $payment->amount = 80;
        $payment->applied = 80;
        $payment->saveQuietly();
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['amount' => 80]);
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 40;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '80',
            '2026-09-25',
            movementIdentity: 'september-instalment',
        ))->handle();
        $september = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25'),
        );

        $this->assertNull(app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $september,
            CarbonImmutable::parse('2026-10-07 12:00:00', 'Europe/Paris'),
        ));

        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->saveQuietly();
        $payment->amount = 120;
        $payment->applied = 120;
        $payment->saveQuietly();
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['amount' => 120]);
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '40',
            '2026-10-25',
            movementIdentity: 'october-final-instalment',
        ))->handle();
        $october = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-10-25'),
        );

        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $october,
            CarbonImmutable::parse('2026-11-07 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($submission);
        $this->assertSame(
            '2026-10-25',
            data_get($submission->payment_request, 'payload.document.frEReport.paymentReport.b2cPayments.0.date'),
        );
        $this->assertEquals(120.0, collect(data_get(
            $submission->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments.0.taxSubtotal',
            [],
        ))->sum('amount'));

        $submission->payment_status = FranceReportingStatus::Accepted->value;
        $submission->save();
        TransactionEvent::query()
            ->whereIn('id', data_get($submission->payment_request, 'snapshot_event_ids', []))
            ->update(['payment_status' => FranceReportingStatus::Accepted->value]);
        app(FranceReportMaterializer::class)->resolveSubmissionFacts(
            $submission,
            FranceReportingStatus::Accepted,
        );
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->saveQuietly();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 20;
        $payment->saveQuietly();
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['refunded' => 20]);
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-11-15',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'november-partial-refund',
        ))->handle();

        $reversal = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $october,
            CarbonImmutable::parse('2026-11-16 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($reversal);
        $this->assertSame('payment_re', data_get($reversal->payment_request, 'variant'));
        $this->assertEquals(-120.0, collect(data_get(
            $reversal->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments.0.taxSubtotal',
            [],
        ))->sum('amount'));

        $reversal->payment_status = FranceReportingStatus::Accepted->value;
        $reversal->save();
        TransactionEvent::query()
            ->whereIn('id', data_get($reversal->payment_request, 'snapshot_event_ids', []))
            ->update(['payment_status' => FranceReportingStatus::Accepted->value]);
        app(FranceReportMaterializer::class)->resolveSubmissionFacts(
            $reversal,
            FranceReportingStatus::Accepted,
        );
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->saveQuietly();
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->refunded = 0;
        $payment->saveQuietly();
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['refunded' => 0]);
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '20',
            '2026-12-15',
            movementIdentity: 'december-restored-payment',
        ))->handle();
        $december = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-12-15'),
        );

        $restored = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $december,
            CarbonImmutable::parse('2027-01-07 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($restored);
        $this->assertSame('2026-12-15', data_get(
            $restored->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments.0.date',
        ));
        $this->assertEquals(120.0, collect(data_get(
            $restored->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments.0.taxSubtotal',
            [],
        ))->sum('amount'));
    }

    public function test_cleared_domestic_business_payment_notification_has_an_accepted_closed_loop(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'original-invoice-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        $capturedPayload = null;
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(function ($request) use (&$capturedPayload) {
            $capturedPayload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            return Http::response(['guid' => 'payment-notification-guid'], 200);
        });

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $submission = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $snapshot = TransactionEvent::query()->findOrFail(
            data_get($submission->payment_request, 'snapshot_event_ids.0'),
        );
        $this->assertSame('original-invoice-guid', $capturedPayload['forDocumentSubmissionGuid']);
        $this->assertSame(FranceReportingStatus::Sent->value, $submission->payment_status);
        $this->assertSame(FranceReportingStatus::Pending->value, $snapshot->payment_status);

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'payment-notification-guid',
            'event' => 'accepted',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Accepted->value, $submission->fresh()->payment_status);
        $this->assertSame(FranceReportingStatus::Accepted->value, $snapshot->fresh()->payment_status);
        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '10',
            '2026-09-26',
            movementIdentity: 'accepted-notification-later-movement',
        ))->handle();
        $laterMovement = $this->movements($payment)->last();
        Http::fake();
        (new SubmitFrancePaymentReceivedNotification($laterMovement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));
        $this->assertSame(FranceReportingStatus::Accepted->value, $laterMovement->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_payment_notification_returns_before_materialization_when_reporting_is_disabled(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'disabled-reporting-document-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $settings = $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        Http::fake();

        (new SubmitFrancePaymentReceivedNotification(
            $movement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertFalse(TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->exists());
        $this->assertNull($movement->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_rejected_notification_does_not_block_a_later_full_payment_cycle(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'rejected-cycle-document-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'rejected-cycle-first-guid'], 200));
        (new SubmitFrancePaymentReceivedNotification(
            $this->movements($payment)->firstOrFail()->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));
        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'rejected-cycle-first-guid',
            'event' => 'rejected',
        ]), 'handle']);

        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->saveQuietly();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 20;
        $payment->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'rejected-cycle-refund',
        ))->handle();
        $refundMovement = $this->movements($payment)->last();
        (new SubmitFrancePaymentReceivedNotification(
            $refundMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $replacement = Payment::factory()->create([
            'client_id' => $invoice->client_id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 20,
            'applied' => 20,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => '2026-09-27',
            'currency_id' => 3,
        ]);
        $replacementPaymentable = new Paymentable();
        $replacementPaymentable->payment_id = $replacement->id;
        $replacementPaymentable->paymentable_id = $invoice->id;
        $replacementPaymentable->paymentable_type = 'invoices';
        $replacementPaymentable->amount = 20;
        $replacementPaymentable->refunded = 0;
        $replacementPaymentable->created_at = strtotime('2026-09-27');
        $replacementPaymentable->updated_at = strtotime('2026-09-27');
        $replacementPaymentable->save();
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $replacement->id,
            $this->company->db,
            $invoice->id,
            $replacementPaymentable->id,
            '20',
            '2026-09-27',
            movementIdentity: 'rejected-cycle-replacement',
        ))->handle();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 12:00:00', 'Europe/Paris'));
        Http::fake(fn() => Http::response(['guid' => 'rejected-cycle-second-guid'], 200));
        (new SubmitFrancePaymentReceivedNotification(
            $this->movements($replacement)->firstOrFail()->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $submissions = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $submissions);
        $this->assertSame(FranceReportingStatus::Rejected->value, $submissions->first()->payment_status);
        $this->assertSame(FranceReportingStatus::Sent->value, $submissions->last()->payment_status);
        $this->assertNotSame($submissions->first()->id, $submissions->last()->id);
        $this->assertNotSame(
            data_get($submissions->first()->payment_request, 'idempotency_guid'),
            data_get($submissions->last()->payment_request, 'idempotency_guid'),
        );
    }

    public function test_rejected_notification_does_not_absorb_a_replacement_payment_created_while_sent(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'sent-cycle-document-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'sent-cycle-first-guid'], 200));
        (new SubmitFrancePaymentReceivedNotification(
            $this->movements($payment)->firstOrFail()->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->saveQuietly();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 20;
        $payment->saveQuietly();
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['refunded' => 20]);
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'sent-cycle-refund',
        ))->handle();
        (new SubmitFrancePaymentReceivedNotification(
            $this->movements($payment)->last()->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $replacement = Payment::factory()->create([
            'client_id' => $invoice->client_id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 20,
            'applied' => 20,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => '2026-09-27',
            'currency_id' => 3,
        ]);
        $replacementPaymentable = new Paymentable();
        $replacementPaymentable->payment_id = $replacement->id;
        $replacementPaymentable->paymentable_id = $invoice->id;
        $replacementPaymentable->paymentable_type = 'invoices';
        $replacementPaymentable->amount = 20;
        $replacementPaymentable->refunded = 0;
        $replacementPaymentable->created_at = strtotime('2026-09-27');
        $replacementPaymentable->updated_at = strtotime('2026-09-27');
        $replacementPaymentable->save();
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $replacement->id,
            $this->company->db,
            $invoice->id,
            $replacementPaymentable->id,
            '20',
            '2026-09-27',
            movementIdentity: 'sent-cycle-replacement',
        ))->handle();
        $replacementMovement = $this->movements($replacement)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 12:00:00', 'Europe/Paris'));

        (new SubmitFrancePaymentReceivedNotification(
            $replacementMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertNull($replacementMovement->fresh()->payment_status);
        $this->assertSame(1, TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->count());

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'sent-cycle-first-guid',
            'event' => 'rejected',
        ]), 'handle']);

        $this->assertNull($replacementMovement->fresh()->payment_status);
        Http::fake(fn() => Http::response(['guid' => 'sent-cycle-second-guid'], 200));
        (new SubmitFrancePaymentReceivedNotification(
            $replacementMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $submissions = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $submissions);
        $this->assertSame(FranceReportingStatus::Rejected->value, $submissions->first()->payment_status);
        $this->assertSame(FranceReportingStatus::Sent->value, $submissions->last()->payment_status);
        $this->assertNotSame(
            data_get($submissions->first()->payment_request, 'idempotency_guid'),
            data_get($submissions->last()->payment_request, 'idempotency_guid'),
        );
    }

    public function test_cross_period_multiple_payments_wait_for_the_final_fact(): void
    {
        [$invoice, $firstPayment, $firstPaymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $firstPayment->amount = 80;
        $firstPayment->applied = 80;
        $firstPayment->saveQuietly();
        Paymentable::query()
            ->where('payment_id', $firstPayment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['amount' => 80]);
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 40;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $firstPayment->id,
            $this->company->db,
            $invoice->id,
            $firstPaymentable->id,
            '80',
            '2026-09-25',
            movementIdentity: 'multiple-payments-first',
        ))->handle();

        $secondPayment = Payment::factory()->create([
            'client_id' => $invoice->client_id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 40,
            'applied' => 40,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => '2026-10-25',
            'currency_id' => 3,
        ]);
        $secondPaymentable = new Paymentable();
        $secondPaymentable->payment_id = $secondPayment->id;
        $secondPaymentable->paymentable_id = $invoice->id;
        $secondPaymentable->paymentable_type = 'invoices';
        $secondPaymentable->amount = 40;
        $secondPaymentable->refunded = 0;
        $secondPaymentable->created_at = strtotime('2026-10-25');
        $secondPaymentable->updated_at = strtotime('2026-10-25');
        $secondPaymentable->save();
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->saveQuietly();
        $september = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25'),
        );
        $october = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-10-25'),
        );

        try {
            app(FranceReportMaterializer::class)->materialize(
                $this->company,
                FranceEReportVariant::PaymentInitial,
                $september,
                CarbonImmutable::parse('2026-10-07 12:00:00', 'Europe/Paris'),
            );
            $this->fail('An incomplete payment fact set must not be materialized.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('facts are not current', $exception->getMessage());
        }

        (new RecordFranceEReportingPayment(
            $secondPayment->id,
            $this->company->db,
            $invoice->id,
            $secondPaymentable->id,
            '40',
            '2026-10-25',
            movementIdentity: 'multiple-payments-final',
        ))->handle();
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $october,
            CarbonImmutable::parse('2026-11-07 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($submission);
        $this->assertSame(
            '2026-10-25',
            data_get($submission->payment_request, 'payload.document.frEReport.paymentReport.b2cPayments.0.date'),
        );
        $this->assertEquals(120.0, collect(data_get(
            $submission->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments.0.taxSubtotal',
            [],
        ))->sum('amount'));
    }

    public function test_empty_projection_does_not_acknowledge_stale_payment_dependencies(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 40;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '80',
            '2026-09-25',
            movementIdentity: 'dependency-first',
        ))->handle();
        $firstMovement = $this->movements($payment)->firstOrFail();
        $september = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25'),
        );
        $materializer = app(FranceReportMaterializer::class);
        $dependencyMethod = new \ReflectionMethod($materializer, 'projectionDependencyWatermark');
        $dependencyWatermark = $dependencyMethod->invoke(
            $materializer,
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $september,
        );
        $sourceHashMethod = new \ReflectionMethod($materializer, 'projectionSourceHash');
        $sourceHash = $sourceHashMethod->invoke(
            $materializer,
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $september,
        );
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['amount' => 100]);
        $acknowledgeMethod = new \ReflectionMethod($materializer, 'acknowledgeFacts');
        $acknowledgeMethod->invoke(
            $materializer,
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $september,
            $firstMovement->id,
            $dependencyWatermark,
            $sourceHash,
        );
        $this->assertNull($firstMovement->fresh()->payment_status);
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['amount' => 120]);

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '40',
            '2026-10-25',
            movementIdentity: 'dependency-second',
        ))->handle();
        $acknowledgeMethod->invoke(
            $materializer,
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $september,
            $firstMovement->id,
            $dependencyWatermark,
            $sourceHash,
        );

        $this->assertNull($firstMovement->fresh()->payment_status);
    }

    public function test_stale_payment_notification_fact_is_not_submitted_after_payment_deletion(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'original-invoice-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        $payment->is_deleted = true;
        $payment->save();
        Http::fake();

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertFalse(TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->exists());
        $this->assertSame(FranceReportingStatus::Rejected->value, $movement->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_rejected_invoice_defers_notification_until_it_recovers_to_cleared(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'original-invoice-guid';
        $backup->e_invoice_status = 'rejected';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'recovered-notification-guid'], 200));

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        $this->assertNotNull(data_get(
            $movement->fresh()->payment_request,
            'notification_deferred_at',
        ));
        $this->assertSame(
            'payment_received_notification',
            data_get($movement->fresh()->payment_request, 'reporting_path'),
        );
        $this->assertSame(
            'FR',
            Invoice::withTrashed()->with('client.country')->findOrFail($invoice->id)->client->country?->iso_3166_2,
        );
        $this->assertFalse(TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->exists());
        Http::assertNothingSent();

        $backup = $invoice->backup;
        $backup->guid = 'replacement-invoice-guid';
        $backup->e_invoice_status = 'cleared';
        $invoice->backup = $backup;
        $invoice->saveQuietly();

        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_INVOICE,
            $this->company->db,
            'cleared',
            'replacement-guid-cleared',
        ))->handle();

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $this->assertSame(FranceReportingStatus::Sent->value, $submission->payment_status);
        $this->assertSame('recovered-notification-guid', data_get($submission->payment_request, 'guid'));
        $this->assertSame(
            'replacement-invoice-guid',
            data_get($submission->payment_request, 'payload.forDocumentSubmissionGuid'),
        );
    }

    public function test_payment_reporting_path_is_fixed_by_the_first_fact(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('DE', 'business', '2026-09-25');
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();

        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $invoice->client->country_id = $france->id;
        $invoice->client->saveQuietly();
        $invoice->unsetRelation('client');
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 20;
        $payment->saveQuietly();
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['refunded' => 20]);

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'fixed-route-refund',
        ))->handle();

        $this->assertSame(
            ['f10', 'f10'],
            $this->movements($payment)
                ->map(fn(TransactionEvent $event): string => data_get($event->payment_request, 'reporting_path'))
                ->all(),
        );
        $this->assertFalse(TransactionEvent::query()
            ->where('payment_id', $payment->id)
            ->whereNotNull('payment_request->current_reporting_path')
            ->exists());

        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-26'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-07 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($submission);
        $this->assertNotEmpty(data_get(
            $submission->payment_request,
            'payload.document.frEReport.paymentReport.b2biPayments',
        ));
        $this->assertSame([], data_get(
            $submission->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments',
            [],
        ));
    }

    public function test_replacement_payment_keeps_a_fully_paid_invoice_notification_eligible(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'replacement-payment-document-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->saveQuietly();
        $payment->amount = 100;
        $payment->applied = 100;
        $payment->saveQuietly();
        $paymentable = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->firstOrFail();
        Paymentable::withTrashed()->where('id', $paymentable->id)->update(['amount' => 100]);
        $paymentable->amount = 100;
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '100',
            '2026-09-25',
            movementIdentity: 'original-partial-payment',
        ))->handle();
        $originalMovement = $this->movements($payment)->firstOrFail();
        (new SubmitFrancePaymentReceivedNotification(
            $originalMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 20;
        $payment->saveQuietly();
        Paymentable::withTrashed()->where('id', $paymentable->id)->update(['refunded' => 20]);
        $paymentable->refunded = 20;
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'partial-refund-before-replacement',
        ))->handle();

        $replacement = Payment::factory()->create([
            'client_id' => $invoice->client_id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 40,
            'applied' => 40,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => '2026-09-27',
            'currency_id' => 3,
        ]);
        $replacementPaymentable = new Paymentable();
        $replacementPaymentable->payment_id = $replacement->id;
        $replacementPaymentable->paymentable_id = $invoice->id;
        $replacementPaymentable->paymentable_type = 'invoices';
        $replacementPaymentable->amount = 40;
        $replacementPaymentable->refunded = 0;
        $replacementPaymentable->created_at = strtotime('2026-09-27');
        $replacementPaymentable->updated_at = strtotime('2026-09-27');
        $replacementPaymentable->save();
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $replacement->id,
            $this->company->db,
            $invoice->id,
            $replacementPaymentable->id,
            '40',
            '2026-09-27',
            movementIdentity: 'replacement-final-payment',
        ))->handle();
        $refundMovement = $this->movements($payment)->last();
        $replacementMovement = $this->movements($replacement)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 12:00:00', 'Europe/Paris'));
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'replacement-payment-notification-guid'], 200));

        (new SubmitFrancePaymentReceivedNotification(
            $refundMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));
        (new SubmitFrancePaymentReceivedNotification(
            $replacementMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $this->assertSame(FranceReportingStatus::Sent->value, $submission->payment_status);
        $this->assertCount(2, data_get($submission->payment_request, 'movement_event_ids'));
        $this->assertSame(
            'notification_not_required_for_non_positive_movement',
            data_get($refundMovement->fresh()->payment_request, 'local_disposition'),
        );
        Http::assertSentCount(1);
    }
    public function test_payment_notification_transport_cannot_regress_a_terminal_callback_state(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'original-invoice-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'payment-notification-guid'], 200));
        $job = new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db);
        $processor = app(FrancePaymentNotificationProcessor::class);
        $job->handle($processor);
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $submission->payment_status = FranceReportingStatus::Rejected->value;
        $submission->save();

        $method = new \ReflectionMethod(FrancePaymentNotificationProcessor::class, 'recordAttempt');
        $method->invoke(
            $processor,
            $submission,
            FranceReportingStatus::RetryableFailure,
            [],
            ['message' => 'late failure', 'class' => \RuntimeException::class],
        );

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertNull(data_get($submission->fresh()->payment_request, 'last_error'));
    }

    public function test_unsent_notification_is_superseded_when_payment_is_deleted(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'pending-delete-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        $this->company->is_disabled = true;
        $this->company->saveQuietly();
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $this->company->is_disabled = false;
        $this->company->saveQuietly();
        $payment->is_deleted = true;
        $payment->saveQuietly();
        Http::fake();

        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertSame('source_no_longer_notification_eligible', data_get(
            $submission->fresh()->payment_request,
            'local_disposition',
        ));
        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_invalid_persisted_notification_payload_reopens_its_movement(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'notification-integrity-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        $this->company->is_disabled = true;
        $this->company->saveQuietly();
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $request = $submission->payment_request;
        data_set($request, 'payload.document.paymentReceivedNotification.mode', 'tampered');
        $submission->payment_request = $request;
        $submission->save();
        $this->company->is_disabled = false;
        $this->company->saveQuietly();
        Http::fake();

        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertSame('invalid_persisted_payload', data_get(
            $submission->fresh()->payment_request,
            'local_disposition',
        ));
        $this->assertNull($movement->fresh()->payment_status);
        $this->company->is_disabled = true;
        $this->company->saveQuietly();
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(2, TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->count());
        Http::assertNothingSent();
    }

    public function test_invalid_notification_payload_after_transport_commitment_is_quarantined(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'notification-commitment-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        $this->company->is_disabled = true;
        $this->company->saveQuietly();
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $request = $submission->payment_request;
        $request['transport_claimed_at'] = now()->toIso8601String();
        data_set($request, 'payload.document.paymentReceivedNotification.mode', 'tampered-after-claim');
        $submission->payment_request = $request;
        $submission->save();
        $this->company->is_disabled = false;
        $this->company->saveQuietly();
        Http::fake();

        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $submission->refresh();
        $this->assertSame(FranceReportingStatus::RetryableFailure->value, $submission->payment_status);
        $this->assertSame(
            'invalid_persisted_payload_after_transport_commitment',
            data_get($submission->payment_request, 'local_disposition'),
        );
        $this->assertSame(FranceReportingStatus::Pending->value, TransactionEvent::query()
            ->findOrFail(data_get($request, 'snapshot_event_ids.0'))
            ->payment_status);
        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_persisted_notification_retry_does_not_rehydrate_mutable_payment_state(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'original-invoice-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'first-guid'], 200));
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $persistedPayload = data_get($submission->payment_request, 'payload');
        $submission->payment_status = FranceReportingStatus::RetryableFailure->value;
        $submission->save();
        $payment->is_deleted = true;
        $payment->save();
        $capturedPayload = null;
        Http::fake(function ($request) use (&$capturedPayload) {
            $capturedPayload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            return Http::response(['guid' => 'retry-guid'], 200);
        });

        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame($persistedPayload, array_intersect_key($capturedPayload, $persistedPayload));
        $this->assertSame(FranceReportingStatus::Sent->value, $submission->fresh()->payment_status);
        $this->assertSame('first-guid', data_get($submission->fresh()->payment_request, 'guid'));

        $request = $submission->fresh()->payment_request;
        $request['sent_at'] = now()->subDay()->toIso8601String();
        $submission->payment_request = $request;
        $submission->save();
        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        Http::assertSentCount(1);
    }

    public function test_notification_waits_for_clearance_and_guid_then_submits(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        $this->assertNotNull(data_get(
            $movement->fresh()->payment_request,
            'notification_deferred_at',
        ));
        $this->assertFalse(TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->exists());

        $backup = $invoice->backup;
        $backup->guid = 'late-original-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = now()->toIso8601String();
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'late-notification-guid'], 200));

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'late-original-guid',
            data_get(TransactionEvent::query()
                ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
                ->firstOrFail()
                ->payment_request, 'original_document_guid'),
        );
    }

    public function test_non_positive_notification_movement_is_closed_locally(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refund:20',
            'payment_received_notification',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'notification_not_required_for_non_positive_movement',
            data_get($movement->fresh()->payment_request, 'local_disposition'),
        );
    }

    public function test_negative_movement_after_an_accepted_notification_requires_review(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::PaymentNotificationSnapshot->value,
            'timestamp' => now()->timestamp,
            'period' => '2026-09-30',
            'payment_status' => FranceReportingStatus::Accepted->value,
            'reporting_data' => null,
            'payment_request' => ['role' => 'accepted_baseline'],
        ]);
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'accepted-notification-refund:20',
            'payment_received_notification',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'notification_adjustment_unreported',
            data_get($movement->fresh()->payment_request, 'local_disposition'),
        );
        $this->assertNull(data_get($movement->fresh()->payment_request, 'projection_gate'));
    }

    public function test_adjustment_after_transport_commitment_requires_review(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'committed-notification-document-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        $positiveMovement = $this->movements($payment)->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        $this->company->is_disabled = true;
        $this->company->saveQuietly();
        (new SubmitFrancePaymentReceivedNotification(
            $positiveMovement->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $request = $submission->payment_request;
        $request['attempts'] = [['attempted_at' => now()->toIso8601String()]];
        $request['guid'] = 'committed-notification-guid';
        $submission->payment_request = $request;
        $submission->payment_status = FranceReportingStatus::Sent->value;
        $submission->save();
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->saveQuietly();

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'committed-notification-refund',
        ))->handle();
        $adjustment = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('payment_id', $payment->id)
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->latest('id')
            ->firstOrFail();
        (new SubmitFrancePaymentReceivedNotification(
            $adjustment->id,
            $this->company->db,
        ))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertSame(FranceReportingStatus::Sent->value, $submission->fresh()->payment_status);
        $this->assertSame(FranceReportingStatus::Accepted->value, $adjustment->fresh()->payment_status);
        $this->assertSame(
            'notification_adjustment_unreported',
            data_get($adjustment->fresh()->payment_request, 'local_disposition'),
        );

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'committed-notification-guid',
            'event' => 'rejected',
        ]), 'handle']);

        $this->assertSame(
            'notification_not_required_after_rejected_submission',
            data_get($adjustment->fresh()->payment_request, 'local_disposition'),
        );
    }

    public function test_negative_f10_movement_remains_available_for_runtime_projection(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('DE', 'business', '2026-09-25');
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-20',
            '2026-09-26',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'foreign-refund:20',
            'f10',
        ))->handle();
        $movement = $this->movements($payment)->firstOrFail();

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(app(FrancePaymentNotificationProcessor::class));

        $this->assertNull($movement->fresh()->payment_status);
        $this->assertSame('f10', data_get(
            $movement->fresh()->payment_request,
            'reporting_path',
        ));
        $this->assertNull(data_get($movement->fresh()->payment_request, 'local_disposition'));
    }

    public function test_notification_callback_closes_all_coalesced_positive_movements(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'coalesced-original-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = now()->toIso8601String();
        $invoice->backup = $backup;
        $invoice->saveQuietly();

        foreach (['first', 'second'] as $identity) {
            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                '60',
                '2026-09-25',
                FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
                $identity,
                'payment_received_notification',
                'coalesced-original-guid',
            ))->handle();
        }

        $movements = $this->movements($payment);
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        $processor = app(FrancePaymentNotificationProcessor::class);
        $materialize = new \ReflectionMethod($processor, 'materialize');
        $submission = $materialize->invoke($processor, $movements->first());
        $materialize->invoke($processor, $movements->last());
        $request = $submission->fresh()->payment_request;
        $request['guid'] = 'coalesced-notification-guid';
        $submission->payment_request = $request;
        $submission->payment_status = FranceReportingStatus::Sent->value;
        $submission->save();
        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'coalesced-notification-guid',
            'event' => 'accepted',
        ]), 'handle']);

        $this->assertCount(2, data_get($submission->fresh()->payment_request, 'movement_event_ids'));
        $this->assertSame(
            [FranceReportingStatus::Accepted->value],
            $this->movements($payment)->pluck('payment_status')->unique()->values()->all(),
        );
    }

    public function test_refund_is_allocated_across_each_original_reporting_period(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');

        foreach ([['70', '2026-08-25', 'august'], ['50', '2026-09-25', 'september']] as [$amount, $date, $identity]) {
            (new RecordFranceEReportingPayment(
                $payment->id,
                $this->company->db,
                $invoice->id,
                $paymentable->id,
                $amount,
                $date,
                FrancePaymentApplicationRecorder::MOVEMENT_APPLIED,
                $identity,
                'f10',
            ))->handle();
        }

        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '-100',
            '2026-10-05',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refund:100',
            'f10',
        ))->handle();

        $negative = $this->movements($payment)->filter(
            fn(TransactionEvent $event): bool => (float) data_get($event->payment_request, 'movement_amount') < 0,
        );
        $this->assertSame(['-50.00', '-50.00'], $negative->pluck('payment_request')
            ->map(fn(array $request): string => $request['movement_amount'])
            ->all());
        $this->assertSame(
            ['2026-09-30', '2026-08-31'],
            $negative->pluck('period')->map->toDateString()->all(),
        );
    }

    public function test_enabling_reporting_bootstraps_current_period_payment_once(): void
    {
        $settings = $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $paymentable = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->firstOrFail();
        $this->assertCount(0, $this->movements($payment));

        $settings->france_reporting_enabled = true;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));

        foreach ([1, 2] as $attempt) {
            (new RecordFranceEReportingScopeInvalidation(
                $this->company->id,
                $this->company->db,
                null,
                "activation:{$attempt}",
                false,
                true,
            ))->handle();
        }

        $movement = $this->movements($payment)->sole();
        $this->assertSame($invoice->id, $movement->invoice_id);
        $this->assertSame($paymentable->id, data_get($movement->payment_request, 'paymentable_id'));
        $this->assertSame('120.00', data_get($movement->payment_request, 'movement_amount'));
    }

    public function test_enabling_reporting_bootstraps_current_period_partial_refund_net(): void
    {
        $settings = $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $paymentable = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->firstOrFail();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 20;
        $payment->saveQuietly();
        $paymentable->refunded = 20;
        $paymentable->updated_at = strtotime('2026-09-26');
        $paymentable->save();
        $settings->france_reporting_enabled = true;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'));

        (new RecordFranceEReportingScopeInvalidation(
            $this->company->id,
            $this->company->db,
            null,
            'activation:partial-refund',
            false,
            true,
        ))->handle();

        $this->assertSame(
            ['120.00', '-20.00'],
            $this->movements($payment)->map(
                fn(TransactionEvent $event): string => data_get($event->payment_request, 'movement_amount'),
            )->all(),
        );
        $this->assertSame($invoice->id, $this->movements($payment)->last()->invoice_id);
    }

    public function test_source_reconciliation_repairs_a_missing_deleted_payment_movement(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $paymentable = Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->firstOrFail();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        Paymentable::query()->where('id', $paymentable->id)->delete();
        $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'));

        (new RecordFranceEReportingScopeInvalidation(
            $this->company->id,
            $this->company->db,
            null,
            'scheduled-source-reconciliation',
            false,
            false,
            true,
        ))->handle();

        $movements = $this->movements($payment);
        $this->assertSame(['120.00', '-120.00'], $movements->map(
            fn(TransactionEvent $event): string => data_get($event->payment_request, 'movement_amount'),
        )->all());
        $this->assertSame(
            FrancePaymentApplicationRecorder::MOVEMENT_DELETED,
            data_get($movements->last()?->payment_request, 'movement_type'),
        );
    }

    public function test_source_reconciliation_watermark_recovers_changes_after_a_long_outage(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'individual', '2026-09-25');
        $paymentable = Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->firstOrFail();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-09-25',
        ))->handle();
        Paymentable::query()->where('id', $paymentable->id)->getQuery()->update([
            'refunded' => 20,
            'updated_at' => '2026-09-26 00:00:00',
        ]);
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->saveQuietly();
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => 0,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::ScopeInvalidation->value,
            'timestamp' => CarbonImmutable::parse('2026-09-25', 'UTC')->timestamp,
            'period' => '2026-09-25',
            'payment_status' => FranceReportingStatus::Accepted->value,
            'reporting_data' => null,
            'payment_request' => [
                'role' => 'source_reconciliation_watermark',
                'reconciled_through_at' => '2026-09-25T00:00:00+00:00',
            ],
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC'));
        $sourceMethod = new \ReflectionMethod(FranceEReportingCron::class, 'sourceReconciliationSince');
        $this->assertSame(
            '2026-09-24T23:59:00+00:00',
            $sourceMethod->invoke(new FranceEReportingCron(), $this->company),
        );
        $this->assertSame(
            '2026-09-26 00:00:00',
            Paymentable::withTrashed()
                ->where('id', $paymentable->id)
                ->firstOrFail()
                ->getRawOriginal('updated_at'),
        );
        $method = new \ReflectionMethod(FranceEReportingCron::class, 'reconcileSourceState');

        $method->invoke(
            new FranceEReportingCron(),
            $this->company,
            $this->company->db,
            app(FranceReportMaterializer::class),
        );

        $this->assertSame(['120.00', '-20.00'], $this->movements($payment)->map(
            fn(TransactionEvent $event): string => data_get($event->payment_request, 'movement_amount'),
        )->all());
        $watermarks = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->where('payment_request->role', 'source_reconciliation_watermark')
            ->get();
        $this->assertCount(1, $watermarks);
        $this->assertSame(
            '2026-12-01T12:00:00+00:00',
            data_get($watermarks->first()?->payment_request, 'reconciled_through_at'),
        );
    }

    public function test_source_reconciliation_recovers_a_missed_payment_projection_invalidation(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('DE', 'business', '2026-08-01');
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            '2026-08-01',
        ))->handle();
        $before = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();
        Payment::query()->whereKey($payment->id)->getQuery()->update([
            'type_id' => 2,
            'updated_at' => '2026-09-26 00:00:00',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC'));

        (new RecordFranceEReportingScopeInvalidation(
            companyId: $this->company->id,
            db: $this->company->db,
            invalidationKey: 'scheduled-source-reconciliation',
            reconcileRecentSourceState: true,
            sourceReconciliationSince: '2026-09-25T00:00:00+00:00',
        ))->handle();

        $events = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->get();
        $this->assertGreaterThan($before, $events->count());
        $this->assertTrue($events->contains(fn(TransactionEvent $event): bool => str_starts_with(
            (string) data_get($event->payment_request, 'invalidation_key'),
            "source-reconciliation-payment:{$payment->id}:",
        )));
    }

    /** @return array{0: Invoice, 1: Payment, 2: Paymentable} */
    private function paymentScenario(string $countryCode, string $classification, string $date): array
    {
        $country = Country::query()->where('iso_3166_2', $countryCode)->firstOrFail();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $country->id,
            'classification' => $classification,
            'name' => 'France payment client',
        ]);
        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'is_primary' => true,
            'send_email' => true,
        ]);
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 20;
        $item->type_id = (string) Product::PRODUCT_TYPE_SERVICE;
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-PAY-' . $client->id,
            'date' => '2026-09-15',
            'due_date' => '2026-10-15',
            'status_id' => Invoice::STATUS_PAID,
            'balance' => 0,
            'line_items' => [$item],
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->save();
        $payment = Payment::factory()->create([
            'client_id' => $client->id,
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
        $paymentable->paymentable_id = $invoice->id;
        $paymentable->paymentable_type = 'invoices';
        $paymentable->amount = 120;
        $paymentable->refunded = 0;
        $paymentable->created_at = strtotime($date);
        $paymentable->updated_at = strtotime($date);
        $paymentable->save();

        return [$invoice, $payment, $paymentable];
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

    /** @return array{0: TransactionEvent, 1: TransactionEvent} */
    private function enableFranceReporting(): void
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $settings = $this->company->settings ?: CompanySettings::defaults();
        $settings->country_id = (string) $france->id;
        $settings->france_reporting_enabled = true;
        $settings->france_reporting_schedule = ReportingProfile::TenDay->value;
        $settings->currency_id = '3';
        $settings->vat_number = 'FR52552100554';
        $settings->id_number = '552100554';

        $taxData = new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = 'FR';

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = 12345;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function disableFranceReporting(): void
    {
        $settings = clone $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        $this->company = $this->company->fresh();
    }

    private function reconcileSourceState(string $since): void
    {
        (new RecordFranceEReportingScopeInvalidation(
            companyId: $this->company->id,
            db: $this->company->db,
            invalidationKey: 'test-source-reconciliation',
            reconcileRecentSourceState: true,
            sourceReconciliationSince: $since,
        ))->handle();
    }
}
