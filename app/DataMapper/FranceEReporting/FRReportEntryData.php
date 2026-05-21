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

use App\Models\TransactionEvent;
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
        public ?B2CTransactionData $b2cTransaction = null,
        public ?B2BIPaymentData $b2biPayment = null,
        public ?B2CPaymentData $b2cPayment = null,
        public int $schemaVersion = self::CURRENT_SCHEMA_VERSION,
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (self::isB2BIInvoicePayload($data)) {
            return self::fromB2BIInvoice(B2BIInvoiceData::fromArray($data));
        }

        $schemaVersion = (int) ($data['schemaVersion'] ?? self::CURRENT_SCHEMA_VERSION);

        if ($schemaVersion > self::CURRENT_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported future France report entry schemaVersion.');
        }

        return new self(
            b2biInvoice: array_key_exists('b2biInvoice', $data) && ! is_null($data['b2biInvoice'])
                ? B2BIInvoiceData::fromArray(ReportDataValidator::assertArray($data['b2biInvoice'], 'frReportEntry.b2biInvoice'))
                : null,
            b2cTransaction: array_key_exists('b2cTransaction', $data) && ! is_null($data['b2cTransaction'])
                ? B2CTransactionData::fromArray(ReportDataValidator::assertArray($data['b2cTransaction'], 'frReportEntry.b2cTransaction'))
                : null,
            b2biPayment: array_key_exists('b2biPayment', $data) && ! is_null($data['b2biPayment'])
                ? B2BIPaymentData::fromArray(ReportDataValidator::assertArray($data['b2biPayment'], 'frReportEntry.b2biPayment'))
                : null,
            b2cPayment: array_key_exists('b2cPayment', $data) && ! is_null($data['b2cPayment'])
                ? B2CPaymentData::fromArray(ReportDataValidator::assertArray($data['b2cPayment'], 'frReportEntry.b2cPayment'))
                : null,
            schemaVersion: $schemaVersion,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromTransactionEventPayload(array $data, int $eventId): self
    {
        return match ($eventId) {
            TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION => self::fromB2BIInvoice(B2BIInvoiceData::fromArray($data)),
            TransactionEvent::FR_B2C_TRANSACTION => self::fromB2CTransaction(B2CTransactionData::fromArray($data)),
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT => self::fromB2BIPayment(B2BIPaymentData::fromArray($data)),
            TransactionEvent::FR_B2C_PAYMENT => self::fromB2CPayment(B2CPaymentData::fromArray($data)),
            default => throw new InvalidArgumentException("Unsupported France report entry event_id [{$eventId}]."),
        };
    }

    public static function supportsTransactionEventId(int $eventId): bool
    {
        return in_array($eventId, [
            TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
            TransactionEvent::FR_B2C_TRANSACTION,
            TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            TransactionEvent::FR_B2C_PAYMENT,
        ], true);
    }

    public static function fromB2BIInvoice(B2BIInvoiceData $b2biInvoice): self
    {
        return new self(b2biInvoice: $b2biInvoice);
    }

    public static function fromB2CTransaction(B2CTransactionData $b2cTransaction): self
    {
        return new self(b2cTransaction: $b2cTransaction);
    }

    public static function fromB2BIPayment(B2BIPaymentData $b2biPayment): self
    {
        return new self(b2biPayment: $b2biPayment);
    }

    public static function fromB2CPayment(B2CPaymentData $b2cPayment): self
    {
        return new self(b2cPayment: $b2cPayment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'schemaVersion' => $this->schemaVersion,
            'b2biInvoice' => $this->b2biInvoice?->toArray(),
            'b2cTransaction' => $this->b2cTransaction?->toArray(),
            'b2biPayment' => $this->b2biPayment?->toArray(),
            'b2cPayment' => $this->b2cPayment?->toArray(),
        ], static fn (mixed $value): bool => ! is_null($value));
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorageArray(): array
    {
        return match (true) {
            ! is_null($this->b2biInvoice) => $this->b2biInvoice->toArray(),
            ! is_null($this->b2cTransaction) => $this->b2cTransaction->toArray(),
            ! is_null($this->b2biPayment) => $this->b2biPayment->toArray(),
            ! is_null($this->b2cPayment) => $this->b2cPayment->toArray(),
            default => throw new InvalidArgumentException('FRReportEntryData requires one report entry object.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
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

    private function validate(): void
    {
        if ($this->schemaVersion !== self::CURRENT_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported France report entry schemaVersion.');
        }

        if (count(array_filter([
            $this->b2biInvoice,
            $this->b2cTransaction,
            $this->b2biPayment,
            $this->b2cPayment,
        ], static fn (mixed $entry): bool => ! is_null($entry))) !== 1) {
            throw new InvalidArgumentException('FRReportEntryData requires exactly one report entry object.');
        }
    }
}