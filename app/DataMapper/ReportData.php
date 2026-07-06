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

namespace App\DataMapper;

use App\Casts\ReportDataCast;
use App\DataMapper\FranceEReporting\FRReportData;
use App\DataMapper\FranceEReporting\FRReportEntryData;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ReportData implements Arrayable, Castable, JsonSerializable
{
    public const CURRENT_SCHEMA_VERSION = 1;

    public function __construct(
        public ?FRReportData $frReport = null,
        public ?FRReportEntryData $frReportEntry = null,
        public int $schemaVersion = self::CURRENT_SCHEMA_VERSION,
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return ReportDataCast::class;
    }

    public static function fromFRReport(FRReportData $frReport): self
    {
        return new self(frReport: $frReport);
    }

    public static function fromFRReportEntry(FRReportEntryData $frReportEntry): self
    {
        return new self(frReportEntry: $frReportEntry);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromTransactionEventPayload(array $data, ?int $eventId): self
    {
        if (! is_null($eventId) && FRReportEntryData::supportsTransactionEventId($eventId)) {
            return self::fromFRReportEntry(FRReportEntryData::fromTransactionEventPayload($data, $eventId));
        }

        return self::fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (self::isB2BIInvoicePayload($data)) {
            $data = [
                'schemaVersion' => self::CURRENT_SCHEMA_VERSION,
                'frReportEntry' => $data,
            ];
        }

        if (array_key_exists('typeCode', $data) && ! array_key_exists('frReport', $data)) {
            $data = [
                'schemaVersion' => self::CURRENT_SCHEMA_VERSION,
                'frReport' => $data,
            ];
        }

        $schemaVersion = (int) ($data['schemaVersion'] ?? self::CURRENT_SCHEMA_VERSION);

        if ($schemaVersion > self::CURRENT_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported future report data schemaVersion.');
        }

        return new self(
            frReport: array_key_exists('frReport', $data) && ! is_null($data['frReport'])
                ? FRReportData::fromArray(self::assertArray($data['frReport'], 'frReport'))
                : null,
            frReportEntry: array_key_exists('frReportEntry', $data) && ! is_null($data['frReportEntry'])
                ? FRReportEntryData::fromArray(self::assertArray($data['frReportEntry'], 'frReportEntry'))
                : null,
            schemaVersion: $schemaVersion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'schemaVersion' => $this->schemaVersion,
            'frReport' => $this->frReport?->toArray(),
            'frReportEntry' => $this->frReportEntry?->toArray(),
        ], static fn (mixed $value): bool => ! is_null($value));
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorageArray(): array
    {
        if (! is_null($this->frReport) && is_null($this->frReportEntry)) {
            return $this->frReport->toArray();
        }

        if (! is_null($this->frReportEntry) && is_null($this->frReport)) {
            return $this->frReportEntry->toStorageArray();
        }

        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function validate(): void
    {
        if ($this->schemaVersion !== self::CURRENT_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported report data schemaVersion.');
        }

        if (is_null($this->frReport) && is_null($this->frReportEntry)) {
            throw new InvalidArgumentException('ReportData requires at least one regional report or report entry.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function isB2BIInvoicePayload(array $data): bool
    {
        return array_key_exists('invoiceNumber', $data)
            && array_key_exists('issueDate', $data)
            && array_key_exists('documentCurrency', $data)
            && array_key_exists('amountIncludingVat', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private static function assertArray(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("{$field} must be an array.");
        }

        return $value;
    }
}
