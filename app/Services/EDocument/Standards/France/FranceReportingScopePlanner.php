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

namespace App\Services\EDocument\Standards\France;

use App\Models\Company;
use App\Models\Client;
use App\Models\TransactionEvent;
use Carbon\CarbonImmutable;

final class FranceReportingScopePlanner
{
    private const SUBMISSION_LEAD_DAYS = 3;

    /** @var array<int, array<string, string>> */
    private array $unhandledScopes = [];

    public function reset(): void
    {
        $this->unhandledScopes = [];
    }

    public function forget(int $companyId): void
    {
        unset($this->unhandledScopes[$companyId]);
    }

    /** @return array<int, ReportingPeriod> */
    public function duePeriods(
        Company $company,
        FranceEReportVariant $family,
        CarbonImmutable $parisNow,
    ): array {
        if (! (bool) $company->getSetting('france_reporting_enabled')) {
            return [];
        }

        if (! array_key_exists($company->id, $this->unhandledScopes)) {
            $this->unhandledScopes[$company->id] = $this->loadUnhandledScopes($company);
        }
        $familyName = $family->isTransaction() ? 'transaction' : 'payment';
        $periods = [];

        foreach (array_keys($this->unhandledScopes[$company->id]) as $key) {
            [$scopeFamily, $profileValue, $periodStart, $periodEnd] = explode(':', $key, 4);

            if ($scopeFamily !== $familyName) {
                continue;
            }

            $profile = ReportingProfile::tryFrom($profileValue);

            if (! $profile) {
                continue;
            }

            $date = CarbonImmutable::parse($periodEnd, 'Europe/Paris');
            $period = ReportingCalendar::currentPeriod($profile, $date);

            if ($period->end->toDateString() !== $date->toDateString()
                || $period->start->toDateString() !== $periodStart
                || $parisNow->startOfDay()->lessThan(
                    $period->dueDate->subDays(self::SUBMISSION_LEAD_DAYS)->startOfDay(),
                )) {
                continue;
            }

            $periods[$key] = $period;
        }

        ksort($periods);

        return array_values($periods);
    }

    /** @return array<string, string> */
    private function loadUnhandledScopes(Company $company): array
    {
        $scopes = [];
        $eventTypes = [
            FranceReportingEventType::DocumentLifecycle->value,
            FranceReportingEventType::PaymentMovement->value,
        ];

        TransactionEvent::query()
            ->select(['id', 'company_id', 'event_id', 'period', 'payment_status', 'payment_request'])
            ->whereIn('event_id', $eventTypes)
            ->where('company_id', $company->id)
            ->whereIn('client_id', Client::withTrashed()
                ->select('id')
                ->where('company_id', $company->id))
            ->whereNotNull('period')
            ->where(function ($query): void {
                $query->whereNull('payment_status')
                    ->orWhere(function ($gatedQuery): void {
                        $gatedQuery
                            ->whereNotNull('payment_request->projection_gate')
                            ->where(
                                'payment_request->projection_schema_version',
                                '<',
                                FranceReportMaterializer::PROJECTION_SCHEMA_VERSION,
                            );
                    });
            })
            ->orderBy('id')
            ->cursor()
            ->each(function (TransactionEvent $event) use (&$scopes): void {
                $scope = $this->scopeFor($event);

                if (! $scope) {
                    return;
                }

                [$family, $profile] = $scope;
                $periodEnd = $event->period->toDateString();
                $canonicalPeriod = ReportingCalendar::currentPeriod(
                    $profile,
                    CarbonImmutable::parse($periodEnd, 'Europe/Paris'),
                );
                $periodStart = (string) data_get(
                    $event->payment_request,
                    'period_start',
                    $canonicalPeriod->start->toDateString(),
                );
                $key = implode(':', [$family, $profile->value, $periodStart, $periodEnd]);
                $scopes[$key] = $profile->value;
            });

        return $scopes;
    }

    /** @return array{0: string, 1: ReportingProfile}|null */
    private function scopeFor(TransactionEvent $event): ?array
    {
        $eventType = FranceReportingEventType::tryFrom((int) $event->event_id);

        if ($eventType === FranceReportingEventType::PaymentMovement) {
            $path = (string) data_get($event->payment_request, 'reporting_path');

            $needsProjection = is_null($event->payment_status)
                || (trim((string) data_get($event->payment_request, 'projection_gate')) !== ''
                    && (int) data_get($event->payment_request, 'projection_schema_version', 0)
                        < FranceReportMaterializer::PROJECTION_SCHEMA_VERSION);

            return $path === 'f10' && $needsProjection
                ? ['payment', ReportingProfile::Monthly]
                : null;
        }

        if ($eventType === FranceReportingEventType::DocumentLifecycle) {
            $family = (string) data_get($event->payment_request, 'family');
            $profile = ReportingProfile::tryFrom(
                (string) data_get($event->payment_request, 'reporting_profile'),
            );
            $projectionGate = trim((string) data_get($event->payment_request, 'projection_gate'));
            $projectionSchemaVersion = (int) data_get(
                $event->payment_request,
                'projection_schema_version',
                0,
            );
            $needsProjection = is_null($event->payment_status)
                || ($projectionGate !== ''
                    && $projectionSchemaVersion < FranceReportMaterializer::PROJECTION_SCHEMA_VERSION);

            return in_array($family, ['transaction', 'payment'], true)
                && $profile
                && $needsProjection
                ? [$family, $profile]
                : null;
        }

        return null;
    }
}
