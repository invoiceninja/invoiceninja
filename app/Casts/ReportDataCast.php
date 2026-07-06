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

namespace App\Casts;

use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use App\DataMapper\ReportData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

class ReportDataCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ReportData
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        try {
            $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Invalid report data JSON: {$exception->getMessage()}", 0, $exception);
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('Report data JSON must decode to an object.');
        }

        return ReportData::fromTransactionEventPayload($data, $this->eventIdFromAttributes($attributes));
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_null($value)) {
            return [$key => null];
        }

        if ($value instanceof ReportData) {
            $reportData = $value;
        } elseif ($value instanceof FRReportData) {
            $reportData = ReportData::fromFRReport($value);
        } elseif ($value instanceof FRReportEntryData) {
            $reportData = ReportData::fromFRReportEntry($value);
        } elseif (is_array($value)) {
            $reportData = ReportData::fromTransactionEventPayload($value, $this->eventIdFromAttributes($attributes));
        } elseif (is_string($value)) {
            try {
                $reportData = ReportData::fromTransactionEventPayload(json_decode($value, true, 512, JSON_THROW_ON_ERROR), $this->eventIdFromAttributes($attributes));
            } catch (JsonException $exception) {
                throw new InvalidArgumentException("Invalid report data JSON: {$exception->getMessage()}", 0, $exception);
            }
        } else {
            throw new InvalidArgumentException('reporting_data must be a ReportData instance, FRReportData instance, FRReportEntryData instance, array, JSON string, or null.');
        }

        return [
            $key => json_encode($reportData->toStorageArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function eventIdFromAttributes(array $attributes): ?int
    {
        if (! array_key_exists('event_id', $attributes) || is_null($attributes['event_id'])) {
            return null;
        }

        return (int) $attributes['event_id'];
    }
}
