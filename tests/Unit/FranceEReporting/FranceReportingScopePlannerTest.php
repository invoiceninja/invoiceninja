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

namespace Tests\Unit\FranceEReporting;

use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
use App\Services\EDocument\Standards\France\FranceReportMaterializer;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingScopePlanner;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class FranceReportingScopePlannerTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('FRREPORTING::');

        $this->makeTestData();
    }

    public function test_accepted_snapshot_closes_a_scope_until_a_new_fact_arrives(): void
    {
        $fact = $this->event(
            FranceReportingEventType::DocumentLifecycle,
            null,
            ['family' => 'transaction', 'reporting_profile' => ReportingProfile::TenDay->value],
        );
        $planner = app(FranceReportingScopePlanner::class);
        $now = CarbonImmutable::parse('2026-10-10', 'Europe/Paris');

        $this->assertCount(1, $planner->duePeriods(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $now,
        ));

        $this->event(
            FranceReportingEventType::TransactionSnapshot,
            FranceReportingStatus::Accepted,
            ['reporting_profile' => ReportingProfile::TenDay->value],
        );
        $fact->payment_status = FranceReportingStatus::Accepted->value;
        $fact->save();
        $planner->reset();
        $this->assertSame([], $planner->duePeriods(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $now,
        ));

        $this->assertLessThan(
            $this->event(
                FranceReportingEventType::DocumentLifecycle,
                null,
                [
                    'family' => 'transaction',
                    'reporting_profile' => ReportingProfile::TenDay->value,
                    'event_key' => 'new-change',
                ],
            )->id,
            $fact->id,
        );
        $planner->reset();
        $this->assertCount(1, $planner->duePeriods(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $now,
        ));
    }

    public function test_materialized_submission_stops_replanning_the_same_payment_fact(): void
    {
        $this->event(
            FranceReportingEventType::PaymentMovement,
            FranceReportingStatus::Pending,
            ['reporting_path' => 'f10'],
            '2026-09-30',
        );
        $this->event(
            FranceReportingEventType::ReportSubmission,
            FranceReportingStatus::Pending,
            ['family' => 'payment', 'reporting_profile' => ReportingProfile::Monthly->value],
            '2026-09-30',
        );
        $planner = app(FranceReportingScopePlanner::class);

        $this->assertSame([], $planner->duePeriods(
            $this->company,
            FranceEReportVariant::PaymentInitial,
            CarbonImmutable::parse('2026-10-10', 'Europe/Paris'),
        ));
    }

    public function test_locally_processed_noop_fact_does_not_remain_due_forever(): void
    {
        $fact = $this->event(
            FranceReportingEventType::DocumentLifecycle,
            FranceReportingStatus::Accepted,
            ['family' => 'transaction', 'reporting_profile' => ReportingProfile::TenDay->value],
        );

        $this->assertSame([], app(FranceReportingScopePlanner::class)->duePeriods(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            CarbonImmutable::parse('2026-10-10', 'Europe/Paris'),
        ));
    }

    public function test_projection_schema_change_reopens_a_previously_gated_fact(): void
    {
        $fact = $this->event(
            FranceReportingEventType::DocumentLifecycle,
            FranceReportingStatus::Accepted,
            [
                'family' => 'transaction',
                'reporting_profile' => ReportingProfile::TenDay->value,
                'projection_gate' => 'foreign_business_credit_mapping_unconfirmed',
                'projection_schema_version' => 0,
            ],
        );
        $planner = app(FranceReportingScopePlanner::class);
        $now = CarbonImmutable::parse('2026-10-10', 'Europe/Paris');

        $this->assertCount(1, $planner->duePeriods(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $now,
        ));

        $this->event(
            FranceReportingEventType::ReportSubmission,
            FranceReportingStatus::Accepted,
            [
                'family' => 'transaction',
                'reporting_profile' => ReportingProfile::TenDay->value,
                'projection_schema_version' => FranceReportMaterializer::PROJECTION_SCHEMA_VERSION,
            ],
        );
        $request = $fact->payment_request;
        $request['projection_schema_version'] = FranceReportMaterializer::PROJECTION_SCHEMA_VERSION;
        $fact->payment_request = $request;
        $fact->payment_status = FranceReportingStatus::Accepted->value;
        $fact->save();
        $planner->reset();

        $this->assertSame([], $planner->duePeriods(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $now,
        ));
    }

    public function test_profiles_with_the_same_period_end_are_independent_scopes(): void
    {
        $tenDayFact = $this->event(
            FranceReportingEventType::DocumentLifecycle,
            null,
            [
                'family' => 'transaction',
                'reporting_profile' => ReportingProfile::TenDay->value,
                'period_start' => '2026-09-21',
            ],
            '2026-09-30',
        );
        $this->event(
            FranceReportingEventType::DocumentLifecycle,
            null,
            [
                'family' => 'transaction',
                'reporting_profile' => ReportingProfile::Monthly->value,
                'period_start' => '2026-09-01',
            ],
            '2026-09-30',
        );
        $planner = app(FranceReportingScopePlanner::class);
        $now = CarbonImmutable::parse('2026-10-10', 'Europe/Paris');

        $this->assertCount(2, $planner->duePeriods(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $now,
        ));

        $tenDayFact->payment_status = FranceReportingStatus::Accepted->value;
        $tenDayFact->save();

        $this->event(
            FranceReportingEventType::TransactionSnapshot,
            FranceReportingStatus::Accepted,
            [
                'reporting_profile' => ReportingProfile::TenDay->value,
                'period_start' => '2026-09-21',
            ],
            '2026-09-30',
        );
        $planner->reset();
        $remaining = $planner->duePeriods(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            $now,
        );

        $this->assertCount(1, $remaining);
        $this->assertSame(ReportingProfile::Monthly, $remaining[0]->profile);
        $this->assertSame('2026-09-01', $remaining[0]->start->toDateString());
    }

    /** @param array<string, mixed> $request */
    private function event(
        FranceReportingEventType $type,
        ?FranceReportingStatus $status,
        array $request,
        string $period = '2026-09-20',
    ): TransactionEvent {
        return TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => 0,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => $type->value,
            'timestamp' => now()->timestamp,
            'period' => $period,
            'payment_status' => $status?->value,
            'reporting_data' => null,
            'payment_request' => $request,
        ]);
    }
}
