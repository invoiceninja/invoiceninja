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
final readonly class TransactionReportData implements Arrayable, JsonSerializable
{
    /**
     * @param array<int, B2BIInvoiceData> $b2biInvoices
     * @param array<int, B2CTransactionData> $b2cTransactions
     */
    public function __construct(
        public string $period,
        public array $b2biInvoices = [],
        public array $b2cTransactions = [],
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            period: ReportDataValidator::assertNonEmptyString($data['period'] ?? null, 'transactionReport.period'),
            b2biInvoices: self::b2biInvoicesFromArray($data['b2biInvoices'] ?? []),
            b2cTransactions: self::b2cTransactionsFromArray($data['b2cTransactions'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'b2biInvoices' => array_values(array_map(
                static fn (B2BIInvoiceData $invoice): array => $invoice->toArray(),
                $this->b2biInvoices,
            )),
            'b2cTransactions' => array_values(array_map(
                static fn (B2CTransactionData $transaction): array => $transaction->toArray(),
                $this->b2cTransactions,
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
        ReportDataValidator::assertNonEmptyString($this->period, 'transactionReport.period');

        if ($this->b2biInvoices === [] && $this->b2cTransactions === []) {
            throw new InvalidArgumentException('transactionReport requires at least one b2biInvoices or b2cTransactions item.');
        }
    }

    /**
     * @return array<int, B2BIInvoiceData>
     */
    private static function b2biInvoicesFromArray(mixed $data): array
    {
        return array_map(
            static fn (mixed $invoice): B2BIInvoiceData => B2BIInvoiceData::fromArray(
                ReportDataValidator::assertArray($invoice, 'transactionReport.b2biInvoices.*'),
            ),
            ReportDataValidator::assertList($data, 'transactionReport.b2biInvoices'),
        );
    }

    /**
     * @return array<int, B2CTransactionData>
     */
    private static function b2cTransactionsFromArray(mixed $data): array
    {
        return array_map(
            static fn (mixed $transaction): B2CTransactionData => B2CTransactionData::fromArray(
                ReportDataValidator::assertArray($transaction, 'transactionReport.b2cTransactions.*'),
            ),
            ReportDataValidator::assertList($data, 'transactionReport.b2cTransactions'),
        );
    }
}
