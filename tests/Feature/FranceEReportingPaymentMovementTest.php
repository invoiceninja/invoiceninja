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
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\FranceRuntimeProjection;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

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
        Http::fake();
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());
        Http::assertNothingSent();
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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        $this->assertNotNull(data_get(
            $movement->fresh()->payment_request,
            'notification_deferred_at',
        ));
        $this->assertSame(
            'payment_received_notification',
            data_get($movement->fresh()->payment_request, 'current_reporting_path'),
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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

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

    public function test_foreign_f10_movement_is_reopened_for_notification_after_client_becomes_domestic(): void
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
        $movement = $this->movements($payment)->firstOrFail();

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

        $this->assertNull($movement->fresh()->payment_status);
        $this->assertSame('f10', data_get(
            $movement->fresh()->payment_request,
            'current_reporting_path',
        ));

        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $invoice->client->country_id = $france->id;
        $invoice->client->saveQuietly();
        $invoice->unsetRelation('client');
        $backup = $invoice->backup;
        $backup->guid = 'domestic-original-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-24T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00', 'Europe/Paris'));
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_CLIENT,
            $this->company->db,
            null,
            'client-became-domestic',
        ))->handle();

        $this->assertNull($movement->fresh()->payment_status);
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'domestic-notification-guid'], 200));

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);

        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $this->assertSame(FranceReportingStatus::Sent->value, $submission->payment_status);
    }

    public function test_accepted_f10_movement_waits_for_an_accepted_reversal_before_notification(): void
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
        $movement = $this->movements($payment)->firstOrFail();
        $movement->payment_status = FranceReportingStatus::Accepted->value;
        $movement->save();
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::PaymentSnapshot->value,
            'timestamp' => now()->timestamp,
            'period' => $movement->period,
            'payment_status' => FranceReportingStatus::Accepted->value,
            'reporting_data' => null,
            'payment_request' => [
                'subject_key' => data_get($movement->payment_request, 'subject_key'),
                'tombstone' => false,
            ],
        ]);
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $invoice->client->country_id = $france->id;
        $invoice->client->saveQuietly();
        $invoice->unsetRelation('client');

        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_CLIENT,
            $this->company->db,
            null,
            'accepted-f10-became-domestic',
        ))->handle();

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'f10_to_notification_pending_reversal',
            data_get($movement->fresh()->payment_request, 'route_transition'),
        );
        $lifecycle = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->where('payment_request->family', 'payment')
            ->latest('id')
            ->firstOrFail();
        $lifecycle->payment_status = FranceReportingStatus::Pending->value;
        $lifecycle->save();
        $tombstone = TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::PaymentSnapshot->value,
            'timestamp' => now()->timestamp,
            'period' => $movement->period,
            'payment_status' => FranceReportingStatus::Accepted->value,
            'reporting_data' => null,
            'payment_request' => ['tombstone' => true],
        ]);
        $submission = TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::ReportSubmission->value,
            'timestamp' => now()->timestamp,
            'period' => $movement->period,
            'payment_status' => FranceReportingStatus::Accepted->value,
            'reporting_data' => null,
            'payment_request' => [
                'family' => 'payment',
                'fact_event_ids' => [$lifecycle->id],
                'snapshot_event_ids' => [$tombstone->id],
            ],
        ]);

        app(FranceReportMaterializer::class)->resolveSubmissionFacts(
            $submission,
            FranceReportingStatus::Accepted,
        );

        $this->assertNull($movement->fresh()->payment_status);
    }

    public function test_ambiguous_f10_transport_blocks_notification_until_rejected(): void
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
        $movement = $this->movements($payment)->firstOrFail();
        $movement->payment_status = FranceReportingStatus::Pending->value;
        $movement->save();
        $submission = TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::ReportSubmission->value,
            'timestamp' => now()->timestamp,
            'period' => $movement->period,
            'payment_status' => FranceReportingStatus::Sent->value,
            'reporting_data' => null,
            'payment_request' => [
                'family' => 'payment',
                'fact_event_ids' => [$movement->id],
                'guid' => 'ambiguous-f10-guid',
                'attempts' => [['attempted_at' => now()->toIso8601String()]],
            ],
        ]);
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $invoice->client->country_id = $france->id;
        $invoice->client->saveQuietly();
        $invoice->unsetRelation('client');

        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_CLIENT,
            $this->company->db,
            null,
            'ambiguous-f10-became-notification',
        ))->handle();

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'f10_to_notification_pending_old_outcome',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );
        $this->assertFalse(TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->exists());

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'ambiguous-f10-guid',
            'event' => 'rejected',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertNull($movement->fresh()->payment_status);
        $this->assertNull(data_get($movement->fresh()->payment_request, 'projection_gate'));
    }

    public function test_accepted_ambiguous_f10_transport_reopens_the_reversal_scope(): void
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
        $movement = $this->movements($payment)->firstOrFail();
        $movement->payment_status = FranceReportingStatus::Pending->value;
        $movement->save();
        $submission = TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::ReportSubmission->value,
            'timestamp' => now()->timestamp,
            'period' => $movement->period,
            'payment_status' => FranceReportingStatus::Sent->value,
            'reporting_data' => null,
            'payment_request' => [
                'family' => 'payment',
                'fact_event_ids' => [$movement->id],
                'guid' => 'accepted-ambiguous-f10-guid',
                'attempts' => [['attempted_at' => now()->toIso8601String()]],
            ],
        ]);
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $invoice->client->country_id = $france->id;
        $invoice->client->saveQuietly();
        $invoice->unsetRelation('client');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_CLIENT,
            $this->company->db,
            null,
            'accepted-ambiguous-f10-became-notification',
        ))->handle();
        $lifecycle = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->where('payment_request->family', 'payment')
            ->latest('id')
            ->firstOrFail();
        $lifecycle->payment_status = FranceReportingStatus::Accepted->value;
        $lifecycle->save();
        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'accepted-ambiguous-f10-guid',
            'event' => 'accepted',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Accepted->value, $submission->fresh()->payment_status);
        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'f10_to_notification_pending_reversal',
            data_get($movement->fresh()->payment_request, 'route_transition'),
        );
        $this->assertNull(data_get($movement->fresh()->payment_request, 'projection_gate'));
        $this->assertNull($lifecycle->fresh()->payment_status);
    }

    public function test_accepted_notification_to_f10_route_change_is_gated(): void
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
        $movement->payment_status = FranceReportingStatus::Accepted->value;
        $movement->save();
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::PaymentNotificationSnapshot->value,
            'timestamp' => now()->timestamp,
            'period' => $movement->period,
            'payment_status' => FranceReportingStatus::Accepted->value,
            'reporting_data' => null,
            'payment_request' => ['tombstone' => false],
        ]);
        $germany = Country::query()->where('iso_3166_2', 'DE')->firstOrFail();
        $invoice->client->country_id = $germany->id;
        $invoice->client->saveQuietly();
        $invoice->unsetRelation('client');

        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_CLIENT,
            $this->company->db,
            null,
            'accepted-notification-became-f10',
        ))->handle();

        $movement = $movement->fresh();
        $this->assertSame('f10', data_get($movement->payment_request, 'current_reporting_path'));
        $this->assertSame(
            'accepted_notification_to_f10_mapping_unconfirmed',
            data_get($movement->payment_request, 'projection_gate'),
        );
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse($movement->period, 'Europe/Paris'),
        );
        $this->assertSame([], app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
        ));
    }

    public function test_ambiguous_notification_transport_blocks_f10_until_rejected(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'ambiguous-notification-document-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = now()->toIso8601String();
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
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $request = $submission->payment_request;
        $request['guid'] = 'ambiguous-notification-guid';
        $request['transport_claimed_at'] = now()->toIso8601String();
        $request['attempts'] = [['attempted_at' => now()->toIso8601String()]];
        $submission->payment_request = $request;
        $submission->payment_status = FranceReportingStatus::Sent->value;
        $submission->save();
        $germany = Country::query()->where('iso_3166_2', 'DE')->firstOrFail();
        $invoice->client->country_id = $germany->id;
        $invoice->client->saveQuietly();
        $invoice->unsetRelation('client');

        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_CLIENT,
            $this->company->db,
            null,
            'ambiguous-notification-became-f10',
        ))->handle();

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'notification_to_f10_pending_old_outcome',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse($movement->period, 'Europe/Paris'),
        );
        $this->assertSame([], app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
        ));

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'ambiguous-notification-guid',
            'event' => 'rejected',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertNull($movement->fresh()->payment_status);
        $this->assertNull(data_get($movement->fresh()->payment_request, 'projection_gate'));
        $this->assertCount(1, app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
        ));
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
        $job->handle(new Storecove());
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $submission->payment_status = FranceReportingStatus::Rejected->value;
        $submission->save();

        $method = new \ReflectionMethod(SubmitFrancePaymentReceivedNotification::class, 'recordAttempt');
        $method->invoke(
            $job,
            $submission,
            FranceReportingStatus::RetryableFailure,
            [],
            ['message' => 'late failure', 'class' => \RuntimeException::class],
        );

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertNull(data_get($submission->fresh()->payment_request, 'last_error'));
    }

    public function test_unsent_notification_is_superseded_when_movement_reroutes_to_f10(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'pending-route-guid';
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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $germany = Country::query()->where('iso_3166_2', 'DE')->firstOrFail();
        $invoice->client->country_id = $germany->id;
        $invoice->client->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_CLIENT,
            $this->company->db,
            null,
            'pending-notification-rerouted',
        ))->handle();

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertSame('movement_routed_to_f10', data_get(
            $submission->fresh()->payment_request,
            'local_disposition',
        ));
        $this->assertNull($movement->fresh()->payment_status);
        $this->assertSame('f10', data_get($movement->fresh()->payment_request, 'current_reporting_path'));
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
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $this->company->is_disabled = false;
        $this->company->saveQuietly();
        $payment->is_deleted = true;
        $payment->saveQuietly();
        Http::fake();

        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(new Storecove());

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
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());
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

        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertSame('invalid_persisted_payload', data_get(
            $submission->fresh()->payment_request,
            'local_disposition',
        ));
        $this->assertNull($movement->fresh()->payment_status);
        $this->company->is_disabled = true;
        $this->company->saveQuietly();
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

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
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());
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

        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(new Storecove());

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
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());
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

        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(new Storecove());

        $this->assertSame($persistedPayload, array_intersect_key($capturedPayload, $persistedPayload));
        $this->assertSame(FranceReportingStatus::Sent->value, $submission->fresh()->payment_status);
        $this->assertSame('first-guid', data_get($submission->fresh()->payment_request, 'guid'));

        $request = $submission->fresh()->payment_request;
        $request['sent_at'] = now()->subDay()->toIso8601String();
        $submission->payment_request = $request;
        $submission->save();
        (new SubmitFrancePaymentReceivedNotification($submission->id, $this->company->db))->handle(new Storecove());

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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'notification_not_required_for_non_positive_movement',
            data_get($movement->fresh()->payment_request, 'local_disposition'),
        );
    }

    public function test_negative_movement_after_an_accepted_notification_is_gated(): void
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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'accepted_notification_reversal_mapping_unconfirmed',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );
    }

    public function test_failed_payment_reversal_is_gated_when_in_flight_notification_is_accepted(): void
    {
        [$movement, $submission] = $this->failedPaymentWithInFlightNotification();
        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => data_get($submission->payment_request, 'guid'),
            'event' => 'accepted',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'accepted_notification_reversal_mapping_unconfirmed',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );
    }

    public function test_failed_payment_reversal_closes_locally_when_in_flight_notification_is_rejected(): void
    {
        [$movement, $submission] = $this->failedPaymentWithInFlightNotification();

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => data_get($submission->payment_request, 'guid'),
            'event' => 'rejected',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'notification_not_required_after_rejected_submission',
            data_get($movement->fresh()->payment_request, 'local_disposition'),
        );
        $this->assertNull(data_get($movement->fresh()->payment_request, 'projection_gate'));
    }

    public function test_rejected_source_with_in_flight_notification_is_gated_when_callback_accepts(): void
    {
        [$invoice, $movement, $submission] = $this->rejectedDocumentWithInFlightNotification();
        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => data_get($submission->payment_request, 'guid'),
            'event' => 'accepted',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'accepted_notification_source_invalid_mapping_unconfirmed',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );
        $backup = $invoice->fresh()->backup;
        $backup->guid = 'replacement-after-accepted-notification-guid';
        $backup->e_invoice_status = 'cleared';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_INVOICE,
            $this->company->db,
            'cleared',
            'replacement-after-accepted-notification',
        ))->handle();
        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'accepted_notification_source_invalid_mapping_unconfirmed',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );
    }

    public function test_accepted_notification_stays_gated_when_source_is_rejected_and_replaced(): void
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'accepted-before-rejection-document-guid';
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
        Http::fake(fn () => Http::response(['guid' => 'accepted-before-rejection-submission-guid'], 200));
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => data_get($submission->payment_request, 'guid'),
            'event' => 'accepted',
        ]), 'handle']);
        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);

        $backup = $invoice->fresh()->backup;
        $backup->e_invoice_status = 'rejected';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_INVOICE,
            $this->company->db,
            'rejected',
            'accepted-notification-source-rejected',
        ))->handle();

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'accepted_notification_source_invalid_mapping_unconfirmed',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );

        $backup = $invoice->fresh()->backup;
        $backup->guid = 'replacement-after-prior-acceptance-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = '2026-09-27T12:00:00+02:00';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_INVOICE,
            $this->company->db,
            'cleared',
            'replacement-after-prior-acceptance',
        ))->handle();
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Accepted->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'accepted_notification_source_invalid_mapping_unconfirmed',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );
        $this->assertSame(1, TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->count());
    }

    public function test_rejected_source_reopens_for_replacement_after_notification_callback_rejects(): void
    {
        [$invoice, $movement, $submission] = $this->rejectedDocumentWithInFlightNotification();

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => data_get($submission->payment_request, 'guid'),
            'event' => 'rejected',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        $backup = $invoice->fresh()->backup;
        $backup->guid = 'replacement-after-rejected-notification-guid';
        $backup->e_invoice_status = 'cleared';
        $backup->e_invoice_cleared_at = now()->toIso8601String();
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_INVOICE,
            $this->company->db,
            'cleared',
            'replacement-after-rejected-notification',
        ))->handle();

        $this->assertNull($movement->fresh()->payment_status);
        $this->assertSame(
            'replacement-after-rejected-notification-guid',
            data_get($movement->fresh()->payment_request, 'original_document_guid'),
        );
        $this->assertNull(data_get($movement->fresh()->payment_request, 'projection_gate'));
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

        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());

        $this->assertNull($movement->fresh()->payment_status);
        $this->assertSame('f10', data_get(
            $movement->fresh()->payment_request,
            'current_reporting_path',
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
        $job = new SubmitFrancePaymentReceivedNotification($movements->first()->id, $this->company->db);
        $materialize = new \ReflectionMethod($job, 'materialize');
        $submission = $materialize->invoke($job, $movements->first());
        $materialize->invoke($job, $movements->last());
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

    public function test_source_reconciliation_recovers_a_missed_client_route_invalidation(): void
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
        $movement = $this->movements($payment)->firstOrFail();
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        Client::query()->whereKey($invoice->client_id)->getQuery()->update([
            'country_id' => $france->id,
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

        $this->assertSame(
            'payment_received_notification',
            data_get($movement->fresh()->payment_request, 'current_reporting_path'),
        );
        $this->assertTrue(TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->where('payment_request->family', 'payment')
            ->exists());
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
    private function failedPaymentWithInFlightNotification(): array
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'failed-payment-in-flight-document-guid';
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
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'payment-notification-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'failed-payment-in-flight-guid'], 200));
        (new SubmitFrancePaymentReceivedNotification(
            $positiveMovement->id,
            $this->company->db,
        ))->handle(new Storecove());
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $payment->status_id = Payment::STATUS_FAILED;
        $payment->save();
        $this->reconcileSourceState('2026-09-25T00:00:00+00:00');
        $negativeMovement = $this->movements($payment)->last();
        $this->assertNotNull($negativeMovement);

        (new SubmitFrancePaymentReceivedNotification(
            $negativeMovement->id,
            $this->company->db,
        ))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Pending->value, $negativeMovement->fresh()->payment_status);
        $this->assertSame(
            'notification_reversal_pending_old_outcome',
            data_get($negativeMovement->fresh()->payment_request, 'projection_gate'),
        );

        return [$negativeMovement, $submission->fresh()];
    }

    /** @return array{0: Invoice, 1: TransactionEvent, 2: TransactionEvent} */
    private function rejectedDocumentWithInFlightNotification(): array
    {
        [$invoice, $payment, $paymentable] = $this->paymentScenario('FR', 'business', '2026-09-25');
        $backup = $invoice->backup;
        $backup->guid = 'rejected-source-in-flight-document-guid';
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
        Http::fake(fn() => Http::response(['guid' => 'rejected-source-in-flight-guid'], 200));
        (new SubmitFrancePaymentReceivedNotification($movement->id, $this->company->db))->handle(new Storecove());
        $submission = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentNotificationSubmission->value)
            ->firstOrFail();
        $backup = $invoice->backup;
        $backup->e_invoice_status = 'rejected';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_INVOICE,
            $this->company->db,
            'rejected',
            'rejected-source-in-flight-notification',
        ))->handle();

        $this->assertSame(FranceReportingStatus::Pending->value, $movement->fresh()->payment_status);
        $this->assertSame(
            'notification_source_invalid_pending_old_outcome',
            data_get($movement->fresh()->payment_request, 'projection_gate'),
        );

        return [$invoice->fresh(), $movement, $submission->fresh()];
    }

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
        $this->company->save();
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
