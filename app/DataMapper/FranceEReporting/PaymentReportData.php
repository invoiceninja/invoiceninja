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
final readonly class PaymentReportData implements Arrayable, JsonSerializable
{
    /**
     * @param array<int, B2BIPaymentData> $b2biPayments
     * @param array<int, B2CPaymentData> $b2cPayments
     */
    public function __construct(
        public string $period,
        public array $b2biPayments = [],
        public array $b2cPayments = [],
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            period: ReportDataValidator::assertNonEmptyString($data['period'] ?? null, 'paymentReport.period'),
            b2biPayments: self::b2biPaymentsFromArray($data['b2biPayments'] ?? []),
            b2cPayments: self::b2cPaymentsFromArray($data['b2cPayments'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'b2biPayments' => array_values(array_map(
                static fn (B2BIPaymentData $payment): array => $payment->toArray(),
                $this->b2biPayments,
            )),
            'b2cPayments' => array_values(array_map(
                static fn (B2CPaymentData $payment): array => $payment->toArray(),
                $this->b2cPayments,
            )),
        ];
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
        ReportDataValidator::assertNonEmptyString($this->period, 'paymentReport.period');

        if ($this->b2biPayments === [] && $this->b2cPayments === []) {
            throw new InvalidArgumentException('paymentReport requires at least one b2biPayments or b2cPayments item.');
        }
    }

    /**
     * @return array<int, B2BIPaymentData>
     */
    private static function b2biPaymentsFromArray(mixed $data): array
    {
        return array_map(
            static fn (mixed $payment): B2BIPaymentData => B2BIPaymentData::fromArray(
                ReportDataValidator::assertArray($payment, 'paymentReport.b2biPayments.*'),
            ),
            ReportDataValidator::assertList($data, 'paymentReport.b2biPayments'),
        );
    }

    /**
     * @return array<int, B2CPaymentData>
     */
    private static function b2cPaymentsFromArray(mixed $data): array
    {
        return array_map(
            static fn (mixed $payment): B2CPaymentData => B2CPaymentData::fromArray(
                ReportDataValidator::assertArray($payment, 'paymentReport.b2cPayments.*'),
            ),
            ReportDataValidator::assertList($data, 'paymentReport.b2cPayments'),
        );
    }
}
