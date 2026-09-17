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

namespace Tests\Feature\FranceEReporting;

use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Factory\GroupSettingFactory;
use App\Factory\InvoiceItemFactory;
use App\Jobs\EDocument\RecordFranceEReportingDocumentLifecycle;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Jobs\Cron\FranceEReportingCron;
use App\Jobs\EDocument\SubmitFranceEReport;
use App\Jobs\EDocument\UpdateFranceEReportSubmissionStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Credit;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\Product;
use App\Models\TransactionEvent;
use App\Models\Webhook;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\FranceSubmissionCallbackStore;
use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class FranceReportMaterializerTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->enableFranceReporting();
    }

    public function test_runtime_fixtures_resolve_format_ready_currencies(): void
    {
        $this->assertCompanyReportingCurrency();

        $eurInvoice = $this->makeInvoice('2026-09-15');
        $this->assertClientCurrencyScaffold($eurInvoice->client, '3', 'EUR');

        $usdInvoice = $this->makeInvoice('2026-09-16', Product::PRODUCT_TYPE_PHYSICAL, '1');
        $this->assertClientCurrencyScaffold($usdInvoice->client, '1', 'USD');

        $credit = $this->makeCredit('2026-09-15');
        $this->assertClientCurrencyScaffold($credit->client, '3', 'EUR');

        [$paidInvoice, $payment] = $this->makePaidServicePayment('2026-09-15', '2026-09-25');
        $this->assertClientCurrencyScaffold($paidInvoice->client, '3', 'EUR');
        $this->assertSame(3, (int) $payment->currency_id);
        $paymentCurrency = $this->expectedCurrency((string) $payment->currency_id);
        $this->assertSame('EUR', $paymentCurrency->code);
        $this->assertCurrencyIsFormatReady($paymentCurrency);
    }

    public function test_materializes_exact_payload_once_and_promotes_only_accepted_snapshots(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $materializer = app(FranceReportMaterializer::class);

        $submission = $materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($submission);
        $this->assertSame(FranceReportingEventType::ReportSubmission->value, $submission->event_id);
        $this->assertSame(FranceReportingStatus::Pending->value, $submission->payment_status);
        $this->assertSame($invoice->client_id, $submission->client_id);
        $this->assertSame($invoice->id, $submission->invoice_id);
        $this->assertSame(0, $submission->payment_id);
        $this->assertSame(0, $submission->credit_id);
        $this->assertSame('transaction_in', data_get($submission->payment_request, 'variant'));
        $this->assertIsArray(data_get($submission->payment_request, 'payload'));
        $this->assertSame(
            data_get($submission->payment_request, 'payload_hash'),
            hash('sha256', json_encode(
                data_get($submission->payment_request, 'payload'),
                JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
            )),
        );
        $this->assertNull($materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        ));

        $snapshotId = data_get($submission->payment_request, 'snapshot_event_ids.0');
        $snapshot = TransactionEvent::query()->findOrFail($snapshotId);
        $this->assertSame(FranceReportingStatus::Pending->value, $snapshot->payment_status);

        $request = $submission->payment_request;
        $request['guid'] = 'storecove-report-guid';
        $submission->payment_request = $request;
        $submission->payment_status = FranceReportingStatus::Sent->value;
        $submission->save();

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'storecove-report-guid',
            'event' => 'accepted',
            'event_group' => 'fr_e_report',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Accepted->value, $submission->fresh()->payment_status);
        $this->assertSame(FranceReportingStatus::Accepted->value, $snapshot->fresh()->payment_status);
    }

    public function test_materializer_returns_before_querying_reporting_state_when_reporting_is_disabled(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse($invoice->date, 'Europe/Paris'),
        );
        $settings = $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        );

        $this->assertNull($submission);
        $this->assertFalse(TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ReportSubmission->value)
            ->exists());
    }

    public function test_callback_store_returns_before_writing_when_reporting_is_disabled(): void
    {
        $settings = $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        $callback = app(FranceSubmissionCallbackStore::class)->record(
            $this->company,
            'disabled-reporting-guid',
            ['event' => 'accepted'],
        );

        $this->assertNull($callback);
        $this->assertFalse(TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::SubmissionCallback->value)
            ->exists());
    }

    public function test_transaction_materialization_rejects_a_period_from_the_previous_schedule(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $settings = $this->company->settings;
        $settings->france_reporting_schedule = ReportingProfile::Monthly->value;
        $this->company->settings = $settings;
        $this->company->saveQuietly();

        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company->fresh(),
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );

        $this->assertNull($submission);
        $this->assertFalse(TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::ReportSubmission->value)
            ->exists());
    }

    public function test_callback_arriving_before_transport_guid_is_persisted_is_replayed(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'early-callback-guid',
            'event' => 'accepted',
            'event_group' => 'fr_e_report',
        ]), 'handle']);

        $callback = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::SubmissionCallback->value)
            ->firstOrFail();
        $this->assertSame(FranceReportingStatus::Pending->value, $callback->payment_status);
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'fr-report-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'early-callback-guid'], 200));

        (new SubmitFranceEReport($submission->id, $this->company->db))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Accepted->value, $submission->fresh()->payment_status);
        $this->assertNull($callback->fresh());
    }

    public function test_expired_callback_is_replayed_before_unmatched_callback_cleanup(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);
        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'delayed-match-guid',
            'event' => 'accepted',
            'event_group' => 'fr_e_report',
        ]), 'handle']);
        $callback = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::SubmissionCallback->value)
            ->firstOrFail();
        $callback->timestamp = now()->subDays(31)->timestamp;
        $callback->save();
        $request = $submission->payment_request;
        $request['guid'] = 'delayed-match-guid';
        $request['guids'] = ['delayed-match-guid'];
        $submission->payment_request = $request;
        $submission->payment_status = FranceReportingStatus::Sent->value;
        $submission->save();
        $method = new \ReflectionMethod(FranceEReportingCron::class, 'replayStoredCallbacks');

        $method->invoke(new FranceEReportingCron(), $this->company);

        $this->assertSame(FranceReportingStatus::Accepted->value, $submission->fresh()->payment_status);
        $this->assertNull($callback->fresh());
    }

    public function test_source_reconciliation_rebuilds_history_after_a_missed_company_context_invalidation(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->update(['payment_status' => FranceReportingStatus::Accepted->value]);
        $before = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();
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
                'reporting_context_hash' => hash('sha256', 'previous-company-context'),
                'reporting_profile' => ReportingProfile::TenDay->value,
            ],
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC'));
        $method = new \ReflectionMethod(FranceEReportingCron::class, 'reconcileSourceState');

        $method->invoke(
            new FranceEReportingCron(),
            $this->company,
            $this->company->db,
            app(FranceReportMaterializer::class),
        );

        $this->assertGreaterThan($before, TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count());
    }

    public function test_reporting_context_hash_tracks_projected_company_settings(): void
    {
        $materializer = app(FranceReportMaterializer::class);

        foreach ([
            'address1' => 'Updated reporting address',
            'vat_number' => 'FR00112233445',
            'currency_id' => '4',
        ] as $setting => $value) {
            $before = $materializer->reportingContextHash($this->company);
            $settings = clone $this->company->settings;
            $settings->{$setting} = $value;
            $this->company->settings = $settings;
            $this->company->saveQuietly();
            $this->company = $this->company->fresh();

            $this->assertNotSame($before, $materializer->reportingContextHash($this->company));
        }

        $before = $materializer->reportingContextHash($this->company);
        $this->company->e_invoice = (object) [
            'Invoice' => (object) ['AccountingSupplierParty' => (object) ['name' => 'Updated supplier']],
        ];
        $this->company->saveQuietly();
        $this->company = $this->company->fresh();

        $this->assertNotSame($before, $materializer->reportingContextHash($this->company));
    }

    public function test_source_reconciliation_detects_contact_derived_party_changes(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        $germany = Country::query()->where('iso_3166_2', 'DE')->firstOrFail();
        $client = $invoice->client;
        $client->name = '';
        $client->classification = 'business';
        $client->country_id = $germany->id;
        $client->id_number = 'DE123456789';
        $client->vat_number = 'DE123456789';
        $client->saveQuietly();
        $contact = $client->contacts()->firstOrFail();
        $contact->first_name = 'Original';
        $contact->last_name = 'Contact';
        $contact->email = 'original@example.test';
        $contact->saveQuietly();
        $originalName = $client->fresh()->present()->name();
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
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
                'reporting_context_hash' => app(FranceReportMaterializer::class)
                    ->reportingContextHash($this->company),
                'reporting_profile' => ReportingProfile::TenDay->value,
            ],
        ]);
        ClientContact::withTrashed()->whereKey($contact->id)->getQuery()->update([
            'first_name' => 'Updated',
            'updated_at' => '2026-10-01 00:00:00',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC'));
        $method = new \ReflectionMethod(FranceEReportingCron::class, 'reconcileSourceState');

        $method->invoke(
            new FranceEReportingCron(),
            $this->company,
            $this->company->db,
            app(FranceReportMaterializer::class),
        );

        $firstCount = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();
        ClientContact::withTrashed()->whereKey($contact->id)->getQuery()->update([
            'first_name' => 'Original',
            'updated_at' => '2026-12-02 00:00:00',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-12-03 12:00:00', 'UTC'));
        $method->invoke(
            new FranceEReportingCron(),
            $this->company,
            $this->company->db,
            app(FranceReportMaterializer::class),
        );

        $this->assertGreaterThan(0, $firstCount);
        $this->assertGreaterThan($firstCount, TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count());
        $this->assertSame($originalName, $client->fresh()->present()->name());
    }

    public function test_unchanged_source_reconciliation_does_not_grow_lifecycle_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 12:00:00', 'UTC'));
        $invoice = $this->makeInvoice('2026-09-15');
        $method = new \ReflectionMethod(FranceEReportingCron::class, 'reconcileSourceState');
        $cron = new FranceEReportingCron();

        $method->invoke(
            $cron,
            $this->company,
            $this->company->db,
            app(FranceReportMaterializer::class),
        );
        $firstCount = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();
        $method->invoke(
            $cron,
            $this->company,
            $this->company->db,
            app(FranceReportMaterializer::class),
        );

        $this->assertGreaterThan(0, $firstCount);
        $this->assertSame($firstCount, TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count());
        $this->assertSame(1, TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->where('payment_request->role', 'source_reconciliation_watermark')
            ->count());
    }

    public function test_source_reconciliation_detects_group_setting_changes(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        $groupSetting = GroupSettingFactory::create($this->company->id, $this->user->id);
        $groupSetting->name = 'France reconciliation group';
        $groupSetting->save();
        $invoice->client->settings = (object) [];
        $invoice->client->group_settings_id = $groupSetting->id;
        $invoice->client->saveQuietly();
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
                'reporting_context_hash' => app(FranceReportMaterializer::class)
                    ->reportingContextHash($this->company),
                'reporting_profile' => ReportingProfile::TenDay->value,
            ],
        ]);
        $settings = $groupSetting->settings;
        $settings->currency_id = '4';
        $groupSetting->settings = $settings;
        $groupSetting->updated_at = '2026-10-01 00:00:00';
        $groupSetting->saveQuietly();
        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', 'UTC'));
        $method = new \ReflectionMethod(FranceEReportingCron::class, 'reconcileSourceState');

        $method->invoke(
            new FranceEReportingCron(),
            $this->company,
            $this->company->db,
            app(FranceReportMaterializer::class),
        );

        $firstCount = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();
        $settings = $groupSetting->settings;
        $settings->currency_id = '3';
        $groupSetting->settings = $settings;
        $groupSetting->updated_at = '2026-12-02 00:00:00';
        $groupSetting->saveQuietly();
        $this->travelTo(CarbonImmutable::parse('2026-12-03 12:00:00', 'UTC'));
        $method->invoke(
            new FranceEReportingCron(),
            $this->company,
            $this->company->db,
            app(FranceReportMaterializer::class),
        );

        $this->assertGreaterThan(0, $firstCount);
        $this->assertGreaterThan($firstCount, TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count());
    }

    public function test_source_reconciliation_detects_assigned_location_changes(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $invoice->client_id,
            'country_id' => $invoice->client->country_id,
            'is_shipping_location' => false,
            'address1' => 'Original address',
            'updated_at' => '2026-09-20 00:00:00',
        ]);
        $invoice->location_id = $location->id;
        $invoice->saveQuietly();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            0,
            $this->company->db,
            null,
            'scheduled-source-reconciliation',
        ))->handle();
        TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->update(['payment_status' => FranceReportingStatus::Accepted->value]);
        $before = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count();
        Location::withTrashed()->whereKey($location->id)->getQuery()->update([
            'address1' => 'Updated address',
            'updated_at' => '2026-10-01 00:00:00',
        ]);

        (new \App\Jobs\EDocument\RecordFranceEReportingScopeInvalidation(
            companyId: $this->company->id,
            db: $this->company->db,
            invalidationKey: 'scheduled-source-reconciliation',
            reconcileRecentSourceState: true,
            sourceReconciliationSince: '2026-09-25T00:00:00+00:00',
        ))->handle();

        $this->assertGreaterThan($before, TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->count());
    }

    public function test_b2c_credit_materializes_end_to_end_as_a_negative_transaction(): void
    {
        $credit = $this->makeCredit('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Credit::class,
            $credit->id,
            Webhook::EVENT_SENT_CREDIT,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );

        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($submission);
        $this->assertLessThan(0, data_get(
            $submission->payment_request,
            'payload.document.frEReport.transactionReport.b2cTransactions.0.amountIncludingVat',
        ));
        $snapshot = TransactionEvent::query()->findOrFail(
            data_get($submission->payment_request, 'snapshot_event_ids.0'),
        );
        $this->assertSame($credit->id, $snapshot->credit_id);
        $this->assertSame(0, $snapshot->invoice_id);
    }

    public function test_non_eur_transaction_is_gated_without_blocking_valid_eur_subjects(): void
    {
        $eurInvoice = $this->makeInvoice('2026-09-15');
        $nonEurInvoice = $this->makeInvoice('2026-09-16', Product::PRODUCT_TYPE_PHYSICAL, '1');

        foreach ([$eurInvoice, $nonEurInvoice] as $invoice) {
            (new RecordFranceEReportingDocumentLifecycle(
                Invoice::class,
                $invoice->id,
                Webhook::EVENT_SENT_INVOICE,
                $this->company->db,
            ))->handle();
        }

        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($submission);
        $this->assertCount(1, data_get(
            $submission->payment_request,
            'payload.document.frEReport.transactionReport.b2cTransactions',
        ));
        $gatedFact = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->where('invoice_id', $nonEurInvoice->id)
            ->where('payment_request->projection_gate', 'non_eur_transaction_mapping_unconfirmed')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(FranceReportingStatus::Pending->value, $gatedFact->payment_status);
    }

    public function test_unknown_callback_stays_sent_and_rejection_does_not_create_an_accepted_baseline(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $materializer = app(FranceReportMaterializer::class);
        $submission = $materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);
        $request = $submission->payment_request;
        $request['guid'] = 'storecove-unknown-guid';
        $submission->payment_request = $request;
        $submission->payment_status = FranceReportingStatus::Sent->value;
        $submission->save();
        $snapshot = TransactionEvent::query()->findOrFail(
            data_get($submission->payment_request, 'snapshot_event_ids.0'),
        );

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'storecove-unknown-guid',
            'event' => 'unpublished_f10_state',
        ]), 'handle']);
        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'storecove-unknown-guid',
            'event' => 'unpublished_f10_state',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Sent->value, $submission->fresh()->payment_status);
        $this->assertSame(FranceReportingStatus::Pending->value, $snapshot->fresh()->payment_status);
        $this->assertCount(1, data_get($submission->fresh()->payment_request, 'events'));

        app()->call([new UpdateFranceEReportSubmissionStatus([
            'tenant_id' => $this->company->company_key,
            'guid' => 'storecove-unknown-guid',
            'event' => 'rejected',
        ]), 'handle']);

        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertSame(FranceReportingStatus::Rejected->value, $snapshot->fresh()->payment_status);
        $this->assertSame([], $materializer->acceptedBaseline(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
        ));
        $this->assertNull($materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-28 12:00:00', 'Europe/Paris'),
        ));
        $lineItems = $invoice->line_items;
        $lineItems[0]->cost = 150;
        $lineItems[0]->line_total = 150;
        $invoice->line_items = $lineItems;
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();
        $replacement = $materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-28 12:05:00', 'Europe/Paris'),
        );
        $this->assertNotNull($replacement);
        $this->assertSame('transaction_in', data_get($replacement->payment_request, 'variant'));
    }

    public function test_transport_sends_the_persisted_payload_without_recompiling_it(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);
        $persistedPayload = data_get($submission->payment_request, 'payload');
        $capturedPayload = null;
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'runtime-reporting-test-key',
        ]);
        Http::fake(function ($request) use (&$capturedPayload) {
            $capturedPayload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

            return Http::response(['guid' => 'storecove-runtime-guid'], 200);
        });

        (new SubmitFranceEReport($submission->id, $this->company->db))->handle(new Storecove());

        $this->assertSame($persistedPayload, $capturedPayload);
        $this->assertSame(FranceReportingStatus::Sent->value, $submission->fresh()->payment_status);
        $this->assertSame('storecove-runtime-guid', data_get($submission->fresh()->payment_request, 'guid'));
        $this->assertSame($persistedPayload, data_get($submission->fresh()->payment_request, 'payload'));

        $request = $submission->fresh()->payment_request;
        $request['sent_at'] = now()->subDay()->toIso8601String();
        $submission->payment_request = $request;
        $submission->save();
        (new SubmitFranceEReport($submission->id, $this->company->db))->handle(new Storecove());

        Http::assertSentCount(1);
    }

    public function test_transport_completion_cannot_regress_an_accepted_callback_state(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);
        $submission->payment_status = FranceReportingStatus::Accepted->value;
        $submission->save();

        $method = new \ReflectionMethod(SubmitFranceEReport::class, 'recordAttempt');
        $method->invoke(
            new SubmitFranceEReport($submission->id, $this->company->db),
            $submission,
            FranceReportingStatus::Sent,
            ['guid' => 'late-transport-guid'],
            null,
            'late-transport-guid',
        );

        $this->assertSame(FranceReportingStatus::Accepted->value, $submission->fresh()->payment_status);
        $this->assertNull(data_get($submission->fresh()->payment_request, 'guid'));
        $this->assertSame($invoice->id, $submission->invoice_id);
    }

    public function test_retryable_transport_outcome_releases_its_claim_for_the_job_retry(): void
    {
        $this->makeInvoice('2026-09-15');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);
        $job = new SubmitFranceEReport($submission->id, $this->company->db);
        $claimTransport = new \ReflectionMethod(SubmitFranceEReport::class, 'claimTransport');
        $recordAttempt = new \ReflectionMethod(SubmitFranceEReport::class, 'recordAttempt');

        $this->assertTrue($claimTransport->invoke($job, $submission));
        $recordAttempt->invoke(
            $job,
            $submission,
            FranceReportingStatus::RetryableFailure,
            [],
            ['message' => 'Temporary transport failure', 'class' => RuntimeException::class],
        );

        $submission->refresh();
        $this->assertSame(FranceReportingStatus::RetryableFailure->value, $submission->payment_status);
        $this->assertNull(data_get($submission->payment_request, 'transport_claimed_at'));
        $this->assertTrue($claimTransport->invoke($job, $submission));
    }

    public function test_invalid_persisted_payload_rejects_its_candidate_snapshot_without_transport(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);
        $request = $submission->payment_request;
        data_set($request, 'payload.document.frEReport.documentId', 'tampered-after-materialization');
        $submission->payment_request = $request;
        $submission->save();
        Http::fake();

        (new SubmitFranceEReport($submission->id, $this->company->db))->handle(new Storecove());

        $snapshot = TransactionEvent::query()->findOrFail(
            data_get($submission->payment_request, 'snapshot_event_ids.0'),
        );
        $this->assertSame(FranceReportingStatus::Rejected->value, $submission->fresh()->payment_status);
        $this->assertSame(FranceReportingStatus::Rejected->value, $snapshot->payment_status);
        $fact = TransactionEvent::query()->findOrFail(
            data_get($submission->payment_request, 'fact_event_ids.0'),
        );
        $this->assertNull($fact->payment_status);
        $this->assertNotNull(app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:01:00', 'Europe/Paris'),
        ));
        Http::assertNothingSent();
    }

    public function test_invalid_persisted_payload_after_transport_commitment_is_quarantined(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);
        $request = $submission->payment_request;
        $request['transport_claimed_at'] = now()->toIso8601String();
        data_set($request, 'payload.document.frEReport.documentId', 'tampered-after-claim');
        $submission->payment_request = $request;
        $submission->save();
        Http::fake();

        (new SubmitFranceEReport($submission->id, $this->company->db))->handle(new Storecove());

        $submission->refresh();
        $this->assertSame(FranceReportingStatus::RetryableFailure->value, $submission->payment_status);
        $this->assertSame(
            'invalid_persisted_payload_after_transport_commitment',
            data_get($submission->payment_request, 'local_disposition'),
        );
        $this->assertSame(FranceReportingStatus::Pending->value, TransactionEvent::query()
            ->findOrFail(data_get($request, 'snapshot_event_ids.0'))
            ->payment_status);
        $this->assertSame(FranceReportingStatus::Pending->value, TransactionEvent::query()
            ->findOrFail(data_get($request, 'fact_event_ids.0'))
            ->payment_status);
        Http::assertNothingSent();
    }

    public function test_persisted_transport_is_quarantined_after_the_retry_budget(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($submission);
        $request = $submission->payment_request;
        $request['attempts'] = array_fill(0, 6, ['error' => ['message' => 'temporary failure']]);
        $submission->payment_request = $request;
        $submission->payment_status = FranceReportingStatus::RetryableFailure->value;
        $submission->save();
        config([
            'ninja.environment' => 'hosted',
            'ninja.storecove_api_key' => 'forced-retry-test-key',
        ]);
        Http::fake(fn() => Http::response(['guid' => 'forced-retry-guid'], 200));

        (new SubmitFranceEReport($submission->id, $this->company->db))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::RetryableFailure->value, $submission->fresh()->payment_status);
        $this->assertNotNull(data_get($submission->fresh()->payment_request, 'retry_exhausted_at'));
        $snapshot = TransactionEvent::query()->findOrFail(
            data_get($submission->payment_request, 'snapshot_event_ids.0'),
        );
        $this->assertSame(FranceReportingStatus::Pending->value, $snapshot->payment_status);
        Http::assertNothingSent();
        $this->assertSame($invoice->id, $submission->invoice_id);

        (new SubmitFranceEReport($submission->id, $this->company->db, true))->handle(new Storecove());

        $this->assertSame(FranceReportingStatus::Sent->value, $submission->fresh()->payment_status);
        $this->assertNull(data_get($submission->fresh()->payment_request, 'retry_exhausted_at'));
        $this->assertSame('forced-retry-guid', data_get($submission->fresh()->payment_request, 'guid'));
    }

    public function test_rejected_paid_invoice_is_reversed_against_the_accepted_baseline(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $materializer = app(FranceReportMaterializer::class);
        $initial = $materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($initial);
        $initial->payment_status = FranceReportingStatus::Accepted->value;
        $initial->save();
        TransactionEvent::query()
            ->whereIn('id', data_get($initial->payment_request, 'snapshot_event_ids', []))
            ->update(['payment_status' => FranceReportingStatus::Accepted->value]);

        $backup = $invoice->backup;
        $backup->e_invoice_status = 'rejected';
        $invoice->backup = $backup;
        $invoice->saveQuietly();

        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::DocumentLifecycle->value,
            'timestamp' => now()->timestamp,
            'period' => $period->end->toDateString(),
            'payment_status' => null,
            'reporting_data' => null,
            'payment_request' => [
                'role' => 'fact',
                'status' => 'rejected',
            ],
        ]);

        $corrective = $materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-28 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($corrective);
        $this->assertSame('transaction_re', data_get($corrective->payment_request, 'variant'));
        $transactions = data_get(
            $corrective->payment_request,
            'payload.document.frEReport.transactionReport.b2cTransactions',
        );
        $this->assertCount(1, $transactions);
        $this->assertLessThan(0, $transactions[0]['amountIncludingVat']);
        $snapshot = TransactionEvent::query()->findOrFail(
            data_get($corrective->payment_request, 'snapshot_event_ids.0'),
        );
        $this->assertTrue((bool) data_get($snapshot->payment_request, 'tombstone'));
        $this->assertNull($snapshot->reporting_data);
    }

    public function test_archiving_an_accepted_invoice_is_acknowledged_without_a_correction(): void
    {
        $invoice = $this->makeInvoice('2026-09-15');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::TenDay,
            CarbonImmutable::parse('2026-09-15', 'Europe/Paris'),
        );
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_SENT_INVOICE,
            $this->company->db,
        ))->handle();
        $materializer = app(FranceReportMaterializer::class);
        $initial = $materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-27 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($initial);
        $initial->payment_status = FranceReportingStatus::Accepted->value;
        $initial->save();
        TransactionEvent::query()
            ->whereIn('id', data_get($initial->payment_request, 'snapshot_event_ids', []))
            ->update(['payment_status' => FranceReportingStatus::Accepted->value]);

        $invoice->delete();
        (new RecordFranceEReportingDocumentLifecycle(
            Invoice::class,
            $invoice->id,
            Webhook::EVENT_ARCHIVE_INVOICE,
            $this->company->db,
        ))->handle();

        $this->assertNull($materializer->materialize(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $period,
            CarbonImmutable::parse('2026-09-28 12:00:00', 'Europe/Paris'),
        ));
        $fact = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', FranceReportingEventType::DocumentLifecycle->value)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(FranceReportingStatus::Accepted->value, $fact->payment_status);
    }

    public function test_refund_in_a_later_month_corrects_the_original_accepted_payment_period(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidServicePayment('2026-09-15', '2026-09-25');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25', 'Europe/Paris'),
        );
        $materializer = app(FranceReportMaterializer::class);
        $initial = $materializer->materialize(
            $this->company,
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

        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 120;
        $invoice->save();
        $payment->status_id = Payment::STATUS_REFUNDED;
        $payment->refunded = 120;
        $payment->save();
        Paymentable::query()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->update(['refunded' => 120]);
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

        $movements = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->where('payment_id', $payment->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $movements);
        $this->assertSame(['2026-09-30', '2026-09-30'], $movements->pluck('period')->map->toDateString()->all());

        $corrective = $materializer->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-15 12:00:00', 'Europe/Paris'),
        );
        $this->assertNotNull($corrective);
        $this->assertSame('payment_re', data_get($corrective->payment_request, 'variant'));
        $payments = data_get(
            $corrective->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments',
        );
        $this->assertCount(1, $payments);
        $this->assertLessThan(0, $payments[0]['taxSubtotal'][0]['amount']);
    }

    public function test_partial_payment_is_not_reported(): void
    {
        [$invoice] = $this->makePaidServicePayment('2026-09-15', '2026-09-25');
        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->saveQuietly();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25', 'Europe/Paris'),
        );

        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-07 12:00:00', 'Europe/Paris'),
        );

        $this->assertNull($submission);
        $this->assertFalse(TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::PaymentSnapshot->value)
            ->exists());
    }

    public function test_partial_refund_reverses_the_full_accepted_payment(): void
    {
        [$invoice, $payment, $paymentable] = $this->makePaidServicePayment('2026-09-15', '2026-09-25');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25', 'Europe/Paris'),
        );
        $materializer = app(FranceReportMaterializer::class);
        $initial = $materializer->materialize(
            $this->company,
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

        $invoice->status_id = Invoice::STATUS_PARTIAL;
        $invoice->balance = 20;
        $invoice->save();
        $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        $payment->refunded = 20;
        $payment->save();
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
            '2026-10-15',
            FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
            'refund:20',
        ))->handle();

        $corrective = $materializer->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-15 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($corrective);
        $this->assertSame('payment_re', data_get($corrective->payment_request, 'variant'));
        $amount = data_get(
            $corrective->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments.0.taxSubtotal.0.amount',
        );
        $this->assertEquals(-120.0, $amount);
    }

    public function test_payment_for_invoice_rejected_in_the_same_period_is_not_reported(): void
    {
        [$invoice] = $this->makePaidServicePayment('2026-09-15', '2026-09-25');
        $backup = $invoice->backup;
        $backup->e_invoice_status = 'rejected';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::DocumentLifecycle->value,
            'timestamp' => now()->timestamp,
            'period' => '2026-09-30',
            'payment_status' => null,
            'reporting_data' => null,
            'payment_request' => ['role' => 'fact', 'status' => 'rejected'],
        ]);
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25', 'Europe/Paris'),
        );

        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-07 12:00:00', 'Europe/Paris'),
        );

        $this->assertNull($submission);
        $this->assertFalse(TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::PaymentSnapshot->value)
            ->exists());
    }

    public function test_non_eur_payment_is_gated_without_blocking_valid_eur_subjects(): void
    {
        $this->makePaidServicePayment('2026-09-15', '2026-09-25');
        [, $nonEurPayment] = $this->makePaidServicePayment('2026-09-16', '2026-09-26', '1');
        $nonEurMovement = TransactionEvent::query()
            ->where('event_id', FranceReportingEventType::PaymentMovement->value)
            ->where('payment_id', $nonEurPayment->id)
            ->firstOrFail();
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25', 'Europe/Paris'),
        );

        $submission = app(FranceReportMaterializer::class)->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-07 12:00:00', 'Europe/Paris'),
        );

        $this->assertNotNull($submission);
        $this->assertSame(
            'non_eur_payment_mapping_unconfirmed',
            data_get($nonEurMovement->payment_request, 'projection_gate'),
        );
        $this->assertCount(1, data_get(
            $submission->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments',
        ));
        $this->assertCount(1, data_get($submission->payment_request, 'snapshot_event_ids'));
        $this->assertSame(FranceReportingStatus::Pending->value, $nonEurMovement->fresh()->payment_status);
    }

    public function test_later_rejection_reverses_an_accepted_payment_in_its_original_period(): void
    {
        [$invoice] = $this->makePaidServicePayment('2026-09-15', '2026-09-25');
        $period = ReportingCalendar::currentPeriod(
            ReportingProfile::Monthly,
            CarbonImmutable::parse('2026-09-25', 'Europe/Paris'),
        );
        $materializer = app(FranceReportMaterializer::class);
        $initial = $materializer->materialize(
            $this->company,
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

        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'Europe/Paris'));
        $backup = $invoice->backup;
        $backup->e_invoice_status = 'rejected';
        $invoice->backup = $backup;
        $invoice->saveQuietly();
        TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => FranceReportingEventType::DocumentLifecycle->value,
            'timestamp' => now()->timestamp,
            'period' => '2026-09-20',
            'payment_status' => null,
            'reporting_data' => null,
            'payment_request' => ['role' => 'fact', 'status' => 'rejected'],
        ]);

        $corrective = $materializer->materialize(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            $period,
            CarbonImmutable::parse('2026-10-15 12:05:00', 'Europe/Paris'),
        );

        $this->assertNotNull($corrective);
        $this->assertSame('payment_re', data_get($corrective->payment_request, 'variant'));
        $payments = data_get(
            $corrective->payment_request,
            'payload.document.frEReport.paymentReport.b2cPayments',
        );
        $this->assertCount(1, $payments);
        $this->assertLessThan(0, $payments[0]['taxSubtotal'][0]['amount']);
        $this->assertSame('2026-09-01 - 2026-09-30', data_get(
            $corrective->payment_request,
            'payload.document.frEReport.paymentReport.period',
        ));
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
        $settings->e_invoice_type = 'PEPPOL';

        $taxData = new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = 'FR';

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = 12345;
        $this->company->save();
        $this->company = $this->company->fresh();

        $this->assertCompanyReportingCurrency();
    }

    private function makeInvoice(
        string $date,
        int $productType = Product::PRODUCT_TYPE_PHYSICAL,
        string $currencyId = '3',
    ): Invoice {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $france->id,
            'classification' => 'individual',
            'name' => 'Runtime reporting client',
        ]);
        $client->settings = (object) ['currency_id' => $currencyId];
        $client->saveQuietly();
        $this->assertClientCurrencyScaffold($client->fresh(), $currencyId);
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
        $item->type_id = (string) $productType;
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-RUNTIME-' . $client->id,
            'date' => $date,
            'due_date' => '2026-10-15',
            'status_id' => Invoice::STATUS_SENT,
            'line_items' => [$item],
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $invoice = $invoice->fresh();
        $this->assertClientCurrencyScaffold($invoice->client, $currencyId);

        return $invoice;
    }

    private function makeCredit(string $date): Credit
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $france->id,
            'classification' => 'individual',
            'name' => 'Runtime reporting credit client',
        ]);
        $this->assertClientCurrencyScaffold($client->fresh(), '3', 'EUR');
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
            'date' => $date,
            'status_id' => Credit::STATUS_SENT,
            'line_items' => [$item],
        ]);
        $credit = $credit->calc()->getCredit();
        $credit->save();

        $credit = $credit->fresh();
        $this->assertClientCurrencyScaffold($credit->client, '3', 'EUR');

        return $credit;
    }

    /** @return array{0: Invoice, 1: Payment, 2: Paymentable} */
    private function makePaidServicePayment(
        string $invoiceDate,
        string $paymentDate,
        string $currencyId = '3',
    ): array {
        $invoice = $this->makeInvoice($invoiceDate, Product::PRODUCT_TYPE_SERVICE);
        $client = $invoice->client;
        $settings = $client->settings ?? (object) [];
        $settings->currency_id = $currencyId;
        $client->settings = $settings;
        $client->saveQuietly();
        $this->assertClientCurrencyScaffold($client->fresh(), $currencyId);
        $invoice->unsetRelation('client');
        $invoice = $invoice->fresh();
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->balance = 0;
        $invoice->save();
        $payment = Payment::factory()->create([
            'client_id' => $invoice->client_id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => 120,
            'applied' => 120,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => $paymentDate,
            'currency_id' => (int) $currencyId,
        ]);
        $this->assertSame((int) $currencyId, (int) $payment->currency_id);
        $this->assertCurrencyIsFormatReady($this->expectedCurrency($currencyId));
        $paymentable = new Paymentable();
        $paymentable->payment_id = $payment->id;
        $paymentable->paymentable_id = $invoice->id;
        $paymentable->paymentable_type = 'invoices';
        $paymentable->amount = 120;
        $paymentable->refunded = 0;
        $paymentable->created_at = strtotime($paymentDate);
        $paymentable->updated_at = strtotime($paymentDate);
        $paymentable->save();
        $paymentable = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->latest('id')
            ->firstOrFail();
        (new RecordFranceEReportingPayment(
            $payment->id,
            $this->company->db,
            $invoice->id,
            $paymentable->id,
            '120',
            $paymentDate,
        ))->handle();

        return [$invoice, $payment, $paymentable];
    }

    private function assertCompanyReportingCurrency(): void
    {
        $this->assertSame('3', (string) $this->company->settings->currency_id);

        $currency = $this->company->currency();
        $this->assertNotNull($currency);
        $this->assertSame('EUR', $currency->code);
        $this->assertCurrencyIsFormatReady($currency);
    }

    private function assertClientCurrencyScaffold(
        Client $client,
        string $expectedCurrencyId,
        ?string $expectedCode = null,
    ): void {
        $this->assertSame($expectedCurrencyId, (string) $client->getSetting('currency_id'));

        $currency = $client->currency();
        $this->assertNotNull($currency);
        $this->assertSame((int) $expectedCurrencyId, (int) $currency->id);

        $expectedCode ??= $this->expectedCurrency($expectedCurrencyId)->code;
        $this->assertSame($expectedCode, $currency->code);
        $this->assertCurrencyIsFormatReady($currency);

        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $this->assertSame($france->id, $client->country_id);
        $this->assertSame('', (string) $client->country?->decimal_separator);
    }

    private function assertCurrencyIsFormatReady(Currency $currency): void
    {
        $this->assertNotNull($currency->decimal_separator);
        $this->assertGreaterThanOrEqual(1, strlen($currency->decimal_separator));
        $this->assertNotNull($currency->thousand_separator);
        $this->assertGreaterThanOrEqual(1, strlen($currency->thousand_separator));
    }

    private function expectedCurrency(string $currencyId): Currency
    {
        $currency = app('currencies')->firstWhere('id', (int) $currencyId)
            ?? Currency::query()->findOrFail($currencyId);

        return $currency;
    }
}
