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
use App\Factory\InvoiceItemFactory;
use App\Jobs\EDocument\RecordFranceEReportingDocumentLifecycle;
use App\Jobs\EDocument\RecordFranceEReportingScopeInvalidation;
use App\Jobs\EDocument\EInvoicePullDocs;
use App\Jobs\Cron\FranceEReportingCron;
use App\Jobs\EDocument\SubmitFranceEReport;
use App\Jobs\EDocument\SubmitFrancePaymentReceivedNotification;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TransactionEvent;
use App\Models\Webhook;
use App\Repositories\ClientRepository;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FrancePaymentNotificationProcessor;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\FranceRuntimeProjection;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FranceReportingScopePlanner;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\MockAccountData;
use Tests\TestCase;

class RecordFranceEReportingTransactionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->enableFranceReporting();
    }

    public function test_document_writes_append_deduplicated_facts_without_financial_snapshots(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $existingCount = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();
        $job = new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        );

        $job->handle();
        $job->handle();

        $events = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->get();
        $this->assertCount($existingCount + 1, $events);
        $event = $events->last();
        $this->assertNull($event->reporting_data);
        $this->assertNull($event->payment_status);
        $this->assertSame(0, $event->payment_id);
        $this->assertSame(0, $event->credit_id);
        $this->assertSame('fact', data_get($event->payment_request, 'role'));
        $this->assertSame('document_changed', data_get($event->payment_request, 'status'));
    }

    public function test_scope_invalidation_discovers_untouched_documents_when_reporting_is_enabled(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        Bus::fake();

        (new RecordFranceEReportingScopeInvalidation(
            $this->company->id,
            $this->company->db,
        ))->handle();

        Bus::assertDispatchedSync(
            RecordFranceEReportingDocumentLifecycle::class,
            function (RecordFranceEReportingDocumentLifecycle $job) use ($invoice): bool {
                $id = new \ReflectionProperty($job, 'id');

                return $id->getValue($job) === $invoice->id;
            },
        );
    }

    public function test_cron_reconciles_a_recent_document_when_its_fact_is_missing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'));
        $invoice = $this->makeInvoice('FR', 'individual');
        TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->delete();
        Bus::fake([
            SubmitFranceEReport::class,
            SubmitFrancePaymentReceivedNotification::class,
        ]);

        (new FranceEReportingCron($this->company->id, $this->company->db))->handle(
            app(FranceReportingScopePlanner::class),
            app(FranceReportMaterializer::class),
            app(FrancePaymentNotificationProcessor::class),
        );

        $factQuery = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value);
        $this->assertTrue($factQuery->exists());
        $factCount = $factQuery->count();

        (new FranceEReportingCron($this->company->id, $this->company->db))->handle(
            app(FranceReportingScopePlanner::class),
            app(FranceReportMaterializer::class),
            app(FrancePaymentNotificationProcessor::class),
        );

        $this->assertSame($factCount, $factQuery->count());
    }

    public function test_cron_dispatches_isolated_company_workers(): void
    {
        Bus::fake([FranceEReportingCron::class]);

        (new FranceEReportingCron())->handle(
            app(FranceReportingScopePlanner::class),
            app(FranceReportMaterializer::class),
            app(FrancePaymentNotificationProcessor::class),
        );

        Bus::assertDispatched(FranceEReportingCron::class, function (FranceEReportingCron $job): bool {
            return (new \ReflectionProperty($job, 'companyId'))->getValue($job) === $this->company->id
                && (new \ReflectionProperty($job, 'db'))->getValue($job) === $this->company->db;
        });
    }

    public function test_distinct_scope_invalidations_cannot_deduplicate_each_other(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $existingCount = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();

        foreach (['client-revision-1', 'client-revision-2'] as $invalidationKey) {
            (new RecordFranceEReportingDocumentLifecycle(
                Invoice::class,
                $invoice->id,
                0,
                $this->company->db,
                null,
                $invalidationKey,
            ))->handle();
        }

        $this->assertSame($existingCount + 2, TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count());
    }

    public function test_client_setting_cannot_disable_company_reporting(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $before = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();
        $settings = clone ($invoice->client->settings ?: CompanySettings::defaults());
        $settings->france_reporting_enabled = false;
        $invoice->client->settings = $settings;
        $invoice->client->saveQuietly();
        $invoice->unsetRelation('client');

        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_CLIENT,
            $this->company->db,
            null,
            'client-reporting-disabled',
        ))->handle();

        $this->assertSame($before + 1, TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count());
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse($invoice->date, 'Europe/Paris'),
        );
        $this->assertNotSame([], app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        ));
    }

    public function test_client_setting_cannot_enable_company_reporting(): void
    {
        $settings = clone ($this->client->settings ?: CompanySettings::defaults());
        $settings->france_reporting_enabled = true;
        $this->client->settings = $settings;
        $this->client->saveQuietly();
        $companySettings = clone $this->company->settings;
        $companySettings->france_reporting_enabled = false;
        $this->company->settings = $companySettings;
        $this->company->saveQuietly();
        $this->client->unsetRelation('company');

        $this->assertFalse($this->client->fresh()->reportableFrTransaction());
    }

    public function test_bulk_client_country_update_records_one_batched_scope_invalidation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 12:00:00', 'Europe/Paris'));
        $invoice = $this->makeInvoice('FR', 'business');
        $germany = Country::query()->where('iso_3166_2', 'DE')->firstOrFail();
        Bus::fake([
            RecordFranceEReportingScopeInvalidation::class,
            SubmitFranceEReport::class,
            SubmitFrancePaymentReceivedNotification::class,
        ]);

        app(ClientRepository::class)->bulkUpdate(
            Client::query()->whereKey($invoice->client_id),
            'country_id',
            $germany->id,
        );

        $invalidation = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame([$invoice->client_id], data_get(
            $invalidation->payment_request,
            'client_ids',
        ));
        $this->assertNull($invalidation->payment_status);
        TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->delete();

        (new FranceEReportingCron($this->company->id, $this->company->db))->handle(
            app(FranceReportingScopePlanner::class),
            app(FranceReportMaterializer::class),
            app(FrancePaymentNotificationProcessor::class),
        );

        $this->assertNull($invalidation->fresh());
        $this->assertTrue(TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->exists());
        $this->assertSame($germany->id, $invoice->client->fresh()->country_id);
    }

    public function test_bulk_client_country_update_skips_reporting_invalidation_when_reporting_is_disabled(): void
    {
        $invoice = $this->makeInvoice('FR', 'business');
        $germany = Country::query()->where('iso_3166_2', 'DE')->firstOrFail();
        $settings = $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        $before = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->count();

        app(ClientRepository::class)->bulkUpdate(
            Client::query()->whereKey($invoice->client_id),
            'country_id',
            $germany->id,
        );

        $this->assertSame($before, TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->count());
        $this->assertSame($germany->id, $invoice->client->fresh()->country_id);
    }

    public function test_direct_model_save_does_not_apply_reporting_disable_request_validation(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $settings = $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;

        $this->company->save();

        $this->assertFalse((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_direct_model_save_does_not_apply_accepted_report_schedule_request_validation(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $this->recordAcceptedReport($invoice);
        $settings = $this->company->settings;
        $settings->france_reporting_schedule = ReportingProfile::Monthly->value;
        $this->company->settings = $settings;

        $this->company->save();

        $this->assertSame(
            ReportingProfile::Monthly->value,
            $this->company->fresh()->getSetting('france_reporting_schedule'),
        );
    }

    public function test_direct_model_save_does_not_apply_accepted_report_legal_entity_request_validation(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $this->recordAcceptedReport($invoice);
        $this->company->legal_entity_id = ((int) $this->company->legal_entity_id) + 1;

        $this->company->save();

        $this->assertSame(
            (int) $this->company->legal_entity_id,
            (int) $this->company->fresh()->legal_entity_id,
        );
    }

    public function test_direct_model_save_does_not_apply_open_report_schedule_request_validation(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $this->recordReport($invoice, FranceReportingStatus::Pending);
        $settings = $this->company->settings;
        $settings->france_reporting_schedule = ReportingProfile::Monthly->value;
        $this->company->settings = $settings;

        $this->company->save();

        $this->assertSame(
            ReportingProfile::Monthly->value,
            $this->company->fresh()->getSetting('france_reporting_schedule'),
        );
    }

    public function test_direct_model_save_does_not_apply_open_report_legal_entity_request_validation(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $this->recordReport($invoice, FranceReportingStatus::Sent);
        $this->company->legal_entity_id = ((int) $this->company->legal_entity_id) + 1;

        $this->company->save();

        $this->assertSame(
            (int) $this->company->legal_entity_id,
            (int) $this->company->fresh()->legal_entity_id,
        );
    }

    public function test_cron_retries_transport_failures_but_does_not_resubmit_sent_reports(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $this->recordReport($invoice, FranceReportingStatus::Sent);
        $this->recordReport($invoice, FranceReportingStatus::RetryableFailure);
        Bus::fake([SubmitFranceEReport::class]);
        $method = new \ReflectionMethod(FranceEReportingCron::class, 'dispatchPersistedSubmissions');

        $method->invoke(
            new FranceEReportingCron(),
            $this->company,
            $this->company->db,
            app(FrancePaymentNotificationProcessor::class),
        );

        Bus::assertDispatchedTimes(SubmitFranceEReport::class, 1);
    }

    public function test_direct_company_schedule_change_does_not_record_scope_invalidation(): void
    {
        $before = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->count();
        $settings = clone $this->company->settings;
        $settings->france_reporting_schedule = ReportingProfile::Monthly->value;
        $this->company->settings = $settings;
        $this->company->save();

        $this->assertSame(
            ReportingProfile::Monthly->value,
            $this->company->fresh()->getSetting('france_reporting_schedule'),
        );
        $this->assertSame($before, TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->count());
    }

    public function test_schedule_changes_do_not_move_an_accepted_document_into_a_duplicate_scope(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::TransactionSnapshot->value,
            'timestamp' => now()->timestamp,
            'period' => '2026-09-20',
            'payment_status' => FranceReportingStatus::Accepted->value,
            'reporting_data' => null,
            'payment_request' => [
                'subject_key' => 'invoice:' . $invoice->id,
                'reporting_profile' => ReportingProfile::TenDay->value,
            ],
        ]);
        $settings = $this->company->settings;
        $settings->france_reporting_schedule = ReportingProfile::Monthly->value;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_INVOICE,
            $this->company->db,
        ))->handle();

        $this->assertSame(
            ['2026-09-20'],
            TransactionEvent::query()
                ->where('invoice_id', $invoice->id)
                ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
                ->pluck('period')
                ->map->toDateString()
                ->unique()
                ->values()
                ->all(),
        );
    }

    public function test_schedule_change_rebuilds_an_older_unreported_document_before_superseding_its_fact(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $invoice->date = '2026-08-05';
        $invoice->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $original = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->firstOrFail();
        $this->assertSame('2026-08-10', $original->period->toDateString());

        $settings = $this->company->settings;
        $settings->france_reporting_schedule = ReportingProfile::Monthly->value;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        (new RecordFranceEReportingScopeInvalidation(
            $this->company->id,
            $this->company->db,
            supersedeUnacceptedTransactionScopes: true,
        ))->handle();

        $this->assertSame(FranceReportingStatus::Accepted->value, $original->fresh()->payment_status);
        $replacement = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->whereNull('payment_status')
            ->firstOrFail();
        $this->assertSame('2026-08-31', $replacement->period->toDateString());
        $this->assertSame(ReportingProfile::Monthly->value, data_get($replacement->payment_request, 'reporting_profile'));
        $this->assertSame('2026-08-01', data_get($replacement->payment_request, 'period_start'));
    }

    public function test_period_move_invalidates_both_pending_old_scope_and_new_scope(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::TransactionSnapshot->value,
            'timestamp' => now()->timestamp,
            'period' => '2026-09-20',
            'payment_status' => FranceReportingStatus::Pending->value,
            'reporting_data' => null,
            'payment_request' => [
                'role' => 'projection_snapshot',
                'subject_key' => 'invoice:' . $invoice->id,
                'reporting_profile' => ReportingProfile::TenDay->value,
            ],
        ]);
        $invoice->date = '2026-10-15';
        $invoice->saveQuietly();

        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_UPDATE_INVOICE,
            $this->company->db,
        ))->handle();

        $this->assertSame(
            ['2026-09-20', '2026-10-20'],
            TransactionEvent::query()
                ->where('invoice_id', $invoice->id)
                ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
                ->orderBy('period')
                ->pluck('period')
                ->map->toDateString()
                ->unique()
                ->values()
                ->all(),
        );
    }

    public function test_runtime_projection_reads_current_document_state_and_excludes_deleted_documents(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse($invoice->date, 'Europe/Paris'),
        );
        $projection = app(FranceRuntimeProjection::class);

        $current = $projection->current($this->company, FranceEReportVariant::TransactionInitial, $period);

        $this->assertCount(1, $current);
        $this->assertSame('invoice:' . $invoice->id, $current[0]->key);
        $this->assertSame((float) $invoice->amount, (float) $current[0]->entry?->b2cTransaction?->amountIncludingVat);

        $invoice->is_deleted = true;
        $invoice->save();

        $this->assertSame([], $projection->current(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        ));
    }

    public function test_archiving_a_document_does_not_reverse_its_economic_projection(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse($invoice->date, 'Europe/Paris'),
        );

        $invoice->delete();

        $current = app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        );
        $this->assertCount(1, $current);
        $this->assertSame('invoice:' . $invoice->id, $current[0]->key);
    }

    public function test_storecove_document_status_is_recorded_through_the_same_idempotent_fact_path(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $job = new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            0,
            $this->company->db,
            'rejected',
        );

        $job->handle();
        $job->handle();

        $events = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->where('payment_request->status', 'rejected')
            ->get();
        $this->assertCount(1, $events);
        $this->assertSame('document_lifecycle', data_get($events->first()->payment_request, 'fact_type'));
        $this->assertSame('rejected', data_get($events->first()->payment_request, 'status'));
    }

    public function test_out_of_order_lifecycle_jobs_cannot_override_current_storecove_state(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $backup = $invoice->backup;
        $backup->e_invoice_status = 'rejected';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            0,
            $this->company->db,
            'accepted',
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse($invoice->date, 'Europe/Paris'),
        );

        $this->assertSame([], app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        ));
    }

    public function test_rejection_remains_latched_until_storecove_explicitly_recovers_the_document(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $backup = $invoice->backup;
        $backup->e_invoice_status = 'rejected';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        $method = new \ReflectionMethod(EInvoicePullDocs::class, 'recordDocumentStatus');
        $job = new EInvoicePullDocs();

        $method->invoke($job, $invoice, 'paid');
        $this->assertSame('rejected', $invoice->fresh()->backup->e_invoice_status);

        $method->invoke($job, $invoice->fresh(), 'accepted');
        $this->assertSame('accepted', $invoice->fresh()->backup->e_invoice_status);
    }

    public function test_foreign_business_credit_is_gated_without_poisoning_valid_invoices(): void
    {
        $invoice = $this->makeInvoice('FR', 'individual');
        $credit = $this->makeCredit('DE', 'business');
        (new RecordFranceEReportingDocumentLifecycle(
            Credit::class,
            $credit->id,
            Webhook::EVENT_SENT_CREDIT,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse($invoice->date, 'Europe/Paris'),
        );

        $current = app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        );

        $this->assertSame(['invoice:' . $invoice->id], array_column($current, 'key'));
        $this->assertNotContains('credit:' . $credit->id, array_column($current, 'key'));
        $this->assertSame(
            'foreign_business_credit_mapping_unconfirmed',
            data_get(TransactionEvent::query()
                ->where('credit_id', $credit->id)
                ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
                ->firstOrFail()
                ->payment_request, 'projection_gate'),
        );
    }

    public function test_b2c_credit_remains_part_of_the_runtime_projection(): void
    {
        $credit = $this->makeCredit('FR', 'individual');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse($credit->date, 'Europe/Paris'),
        );

        $current = app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        );

        $this->assertSame(['credit:' . $credit->id], array_column($current, 'key'));
        $this->assertLessThan(0, $current[0]->entry?->b2cTransaction?->amountIncludingVat);
    }

    public function test_runtime_projection_excludes_domestic_business_documents_from_f10(): void
    {
        $this->makeInvoice('FR', 'business');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );

        $this->assertSame([], app(FranceRuntimeProjection::class)->current(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        ));
    }

    private function recordAcceptedReport(Invoice $invoice): void
    {
        $this->recordReport($invoice, FranceReportingStatus::Accepted);
    }

    private function recordReport(Invoice $invoice, FranceReportingStatus $status): TransactionEvent
    {
        return TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::ReportSubmission->value,
            'timestamp' => now()->timestamp,
            'period' => '2026-09-20',
            'payment_status' => $status->value,
            'reporting_data' => null,
            'payment_request' => [
                'role' => 'submission',
                'family' => 'transaction',
                'reporting_profile' => ReportingProfile::TenDay->value,
                'period_start' => '2026-09-11',
            ],
        ]);
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

    private function makeInvoice(string $countryCode, string $classification): Invoice
    {
        $country = Country::query()->where('iso_3166_2', $countryCode)->firstOrFail();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $country->id,
            'classification' => $classification,
            'name' => 'Runtime reporting client',
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
        $item->type_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-RUNTIME-' . $client->id,
            'date' => '2026-09-15',
            'due_date' => '2026-10-15',
            'status_id' => Invoice::STATUS_SENT,
            'line_items' => [$item],
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        return $invoice->fresh();
    }

    private function makeCredit(string $countryCode, string $classification): Credit
    {
        $country = Country::query()->where('iso_3166_2', $countryCode)->firstOrFail();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $country->id,
            'classification' => $classification,
            'name' => 'Runtime reporting credit client',
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
        $item->type_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
        $credit = Credit::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-RUNTIME-CREDIT-' . $client->id,
            'date' => '2026-09-15',
            'status_id' => Credit::STATUS_SENT,
            'line_items' => [$item],
        ]);
        $credit = $credit->calc()->getCredit();
        $credit->save();

        return $credit->fresh();
    }
}
