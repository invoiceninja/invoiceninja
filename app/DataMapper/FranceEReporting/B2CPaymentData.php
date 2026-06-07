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
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class B2CPaymentData implements Arrayable, JsonSerializable
{
    /**
     * @param array<int, TaxSubtotalData> $taxSubtotal
     */
    public function __construct(
        public string $date,
        public array $taxSubtotal = [],
    ) {
        ReportDataValidator::assertDate($this->date, 'b2cPayments.date');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: ReportDataValidator::assertDate($data['date'] ?? null, 'b2cPayments.date'),
            taxSubtotal: self::taxSubtotalFromArray($data['taxSubtotal'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'date' => $this->date,
            'taxSubtotal' => array_values(array_map(
                static fn (TaxSubtotalData $taxSubtotal): array => $taxSubtotal->toArray(),
                $this->taxSubtotal,
            )),
        ], static fn (mixed $value): bool => $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<int, TaxSubtotalData>
     */
    private static function taxSubtotalFromArray(mixed $data): array
    {
        return array_map(
            static fn (mixed $taxSubtotal): TaxSubtotalData => TaxSubtotalData::fromArray(
                ReportDataValidator::assertArray($taxSubtotal, 'b2cPayments.taxSubtotal.*'),
            ),
            ReportDataValidator::assertList($data, 'b2cPayments.taxSubtotal'),
        );
    }
}
