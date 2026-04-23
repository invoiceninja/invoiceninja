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

namespace App\Services\Chart;

use App\Models\Client;
use App\Models\Company;
use App\Utils\Traits\MakesHash;

class ClientPaymentAnalyticsService
{
    use MakesHash;
    private const THRESHOLDS = [
        'avg_days' => ['green' => 15, 'yellow' => 30],
        'stddev' => ['green' => 5, 'yellow' => 15],
        'late_rate' => ['green' => 0.10, 'yellow' => 0.25],
        'data_points' => ['green' => 30, 'yellow' => 10],
    ];

    private const RISK_WEIGHTS = [
        'avg_days' => 0.35,
        'late_rate' => 0.30,
        'stddev' => 0.20,
        'confidence' => 0.15,
    ];

    public function __construct(private Company $company) {}

    /**
     * @param array<int, \stdClass> $clientSummaries
     * @param array<int, \stdClass> $companyPaymentSummary
     * @return array<string, mixed>
     */
    public function generate(array $clientSummaries, array $companyPaymentSummary): array
    {
        $companySummary = ! empty($companyPaymentSummary) ? $companyPaymentSummary[0] : null;

        $clientIds = array_map(fn ($c) => (int) $c->client_id, $clientSummaries);
        $clientModels = Client::withTrashed()->whereIn('id', $clientIds)->get()->keyBy('id');

        $clients = [];

        foreach ($clientSummaries as $client) {
            $indicators = [
                'avg_days' => $this->classifyMetric('avg_days', (float) ($client->avg_payment_days ?? 0)),
                'stddev' => $this->classifyMetric('stddev', (float) ($client->stddev_payment_days ?? 0)),
                'late_rate' => $this->classifyMetric('late_rate', (float) ($client->late_payment_ratio ?? 0)),
                'data_points' => $this->classifyMetric('data_points', (int) ($client->total_invoices ?? 0)),
            ];

            $riskScore = $this->calculateRiskScore($client);

            $clientModel = $clientModels->get((int) $client->client_id);

            $clients[] = [
                'client_id' => $this->encodePrimaryKey($client->client_id),
                'client_name' => $clientModel ? $clientModel->present()->name() : '',
                'currency_id' => (int) $client->currency_id,
                'avg_payment_days' => round((float) ($client->avg_payment_days ?? 0), 2),
                'stddev_payment_days' => round((float) ($client->stddev_payment_days ?? 0), 2),
                'total_invoices' => (int) ($client->total_invoices ?? 0),
                'late_invoices' => (int) ($client->late_invoices ?? 0),
                'late_payment_ratio' => round((float) ($client->late_payment_ratio ?? 0), 4),
                'risk_score' => $riskScore,
                'risk_level' => $this->riskLevel($riskScore),
                'indicators' => $indicators,
            ];
        }

        usort($clients, fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        return [
            'company_summary' => $companySummary ? [
                'avg_payment_days' => round((float) ($companySummary->avg_payment_days ?? 0), 2),
                'stddev_payment_days' => round((float) ($companySummary->stddev_payment_days ?? 0), 2),
                'total_invoices' => (int) ($companySummary->total_invoices ?? 0),
                'late_payment_ratio' => round((float) ($companySummary->late_payment_ratio ?? 0), 4),
            ] : [
                'avg_payment_days' => 0,
                'stddev_payment_days' => 0,
                'total_invoices' => 0,
                'late_payment_ratio' => 0,
            ],
            'thresholds' => self::THRESHOLDS,
            'clients' => $clients,
        ];
    }

    private function classifyMetric(string $metric, float $value): string
    {
        $thresholds = self::THRESHOLDS[$metric];

        if ($metric === 'data_points') {
            if ($value >= $thresholds['green']) {
                return 'green';
            }

            if ($value >= $thresholds['yellow']) {
                return 'yellow';
            }

            return 'red';
        }

        if ($value < $thresholds['green']) {
            return 'green';
        }

        if ($value <= $thresholds['yellow']) {
            return 'yellow';
        }

        return 'red';
    }

    private function calculateRiskScore(\stdClass $client): float
    {
        $avgDays = (float) ($client->avg_payment_days ?? 0);
        $avgDaysScore = min($avgDays / 45, 1.0) * 100;

        $lateRate = (float) ($client->late_payment_ratio ?? 0);
        $lateRateScore = min($lateRate / 0.5, 1.0) * 100;

        $stddev = (float) ($client->stddev_payment_days ?? 0);
        $stddevScore = min($stddev / 25, 1.0) * 100;

        $dataPoints = (int) ($client->total_invoices ?? 0);
        $confidencePenalty = $dataPoints >= 30 ? 0 : (1 - ($dataPoints / 30)) * 100;

        $score = ($avgDaysScore * self::RISK_WEIGHTS['avg_days'])
            + ($lateRateScore * self::RISK_WEIGHTS['late_rate'])
            + ($stddevScore * self::RISK_WEIGHTS['stddev'])
            + ($confidencePenalty * self::RISK_WEIGHTS['confidence']);

        return round(min(max($score, 0), 100), 2);
    }

    private function riskLevel(float $score): string
    {
        if ($score < 33) {
            return 'low';
        }

        if ($score <= 66) {
            return 'medium';
        }

        return 'high';
    }
}
