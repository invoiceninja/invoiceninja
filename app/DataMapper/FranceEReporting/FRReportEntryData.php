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

namespace App\DataMapper\FranceEReporting;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class FRReportEntryData implements Arrayable, JsonSerializable
{
    public const CURRENT_SCHEMA_VERSION = 1;

    public function __construct(
        public ?B2BIInvoiceData $b2biInvoice = null,
        public int $schemaVersion = self::CURRENT_SCHEMA_VERSION,
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $schemaVersion = (int) ($data['schemaVersion'] ?? self::CURRENT_SCHEMA_VERSION);

        if ($schemaVersion > self::CURRENT_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported future France report entry schemaVersion.');
        }

        return new self(
            b2biInvoice: array_key_exists('b2biInvoice', $data) && ! is_null($data['b2biInvoice'])
                ? B2BIInvoiceData::fromArray(ReportDataValidator::assertArray($data['b2biInvoice'], 'frReportEntry.b2biInvoice'))
                : null,
            schemaVersion: $schemaVersion,
        );
    }

    public static function fromB2BIInvoice(B2BIInvoiceData $b2biInvoice): self
    {
        return new self(b2biInvoice: $b2biInvoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'schemaVersion' => $this->schemaVersion,
            'b2biInvoice' => $this->b2biInvoice?->toArray(),
        ], static fn (mixed $value): bool => ! is_null($value));
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
            throw new InvalidArgumentException('Unsupported France report entry schemaVersion.');
        }

        if (is_null($this->b2biInvoice)) {
            throw new InvalidArgumentException('FRReportEntryData requires at least one report entry object.');
        }
    }
}