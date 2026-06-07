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
final readonly class FRReportData implements Arrayable, JsonSerializable
{
    public const CURRENT_SCHEMA_VERSION = 1;

    public const TYPE_INITIAL = 'IN';

    public const TYPE_RECTIFICATIVE = 'RE';

    public function __construct(
        public string $typeCode,
        public string $documentId,
        public string $issueDate,
        public string $issueTime,
        public string $timeZone,
        public ?DeclarantPartyData $declarantParty = null,
        public ?TransactionReportData $transactionReport = null,
        public ?PaymentReportData $paymentReport = null,
        public int $schemaVersion = self::CURRENT_SCHEMA_VERSION,
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $data = self::upgradeToCurrentSchema($data);

        return new self(
            typeCode: ReportDataValidator::assertNonEmptyString($data['typeCode'] ?? null, 'typeCode'),
            documentId: ReportDataValidator::assertNonEmptyString($data['documentId'] ?? null, 'documentId'),
            issueDate: ReportDataValidator::assertDate($data['issueDate'] ?? null, 'issueDate'),
            issueTime: ReportDataValidator::assertTime($data['issueTime'] ?? null, 'issueTime'),
            timeZone: ReportDataValidator::assertNonEmptyString($data['timeZone'] ?? null, 'timeZone'),
            declarantParty: array_key_exists('declarantParty', $data) && ! is_null($data['declarantParty'])
                ? DeclarantPartyData::fromArray(ReportDataValidator::assertArray($data['declarantParty'], 'declarantParty'))
                : null,
            transactionReport: array_key_exists('transactionReport', $data) && ! is_null($data['transactionReport'])
                ? TransactionReportData::fromArray(ReportDataValidator::assertArray($data['transactionReport'], 'transactionReport'))
                : null,
            paymentReport: array_key_exists('paymentReport', $data) && ! is_null($data['paymentReport'])
                ? PaymentReportData::fromArray(ReportDataValidator::assertArray($data['paymentReport'], 'paymentReport'))
                : null,
            schemaVersion: (int) ($data['schemaVersion'] ?? self::CURRENT_SCHEMA_VERSION),
        );
    }

    public static function initialTransactionReport(
        string $documentId,
        string $issueDate,
        string $issueTime,
        string $timeZone,
        TransactionReportData $transactionReport,
        ?DeclarantPartyData $declarantParty = null,
    ): self {
        return new self(
            typeCode: self::TYPE_INITIAL,
            documentId: $documentId,
            issueDate: $issueDate,
            issueTime: $issueTime,
            timeZone: $timeZone,
            declarantParty: $declarantParty,
            transactionReport: $transactionReport,
        );
    }

    public static function initialPaymentReport(
        string $documentId,
        string $issueDate,
        string $issueTime,
        string $timeZone,
        PaymentReportData $paymentReport,
        ?DeclarantPartyData $declarantParty = null,
    ): self {
        return new self(
            typeCode: self::TYPE_INITIAL,
            documentId: $documentId,
            issueDate: $issueDate,
            issueTime: $issueTime,
            timeZone: $timeZone,
            declarantParty: $declarantParty,
            paymentReport: $paymentReport,
        );
    }

    public static function combinedInitialReport(
        string $documentId,
        string $issueDate,
        string $issueTime,
        string $timeZone,
        TransactionReportData $transactionReport,
        PaymentReportData $paymentReport,
        ?DeclarantPartyData $declarantParty = null,
    ): self {
        return new self(
            typeCode: self::TYPE_INITIAL,
            documentId: $documentId,
            issueDate: $issueDate,
            issueTime: $issueTime,
            timeZone: $timeZone,
            declarantParty: $declarantParty,
            transactionReport: $transactionReport,
            paymentReport: $paymentReport,
        );
    }

    public static function rectificativeTransactionReport(
        string $documentId,
        string $issueDate,
        string $issueTime,
        string $timeZone,
        TransactionReportData $transactionReport,
        ?DeclarantPartyData $declarantParty = null,
    ): self {
        return new self(
            typeCode: self::TYPE_RECTIFICATIVE,
            documentId: $documentId,
            issueDate: $issueDate,
            issueTime: $issueTime,
            timeZone: $timeZone,
            declarantParty: $declarantParty,
            transactionReport: $transactionReport,
        );
    }

    public static function rectificativePaymentReport(
        string $documentId,
        string $issueDate,
        string $issueTime,
        string $timeZone,
        PaymentReportData $paymentReport,
        ?DeclarantPartyData $declarantParty = null,
    ): self {
        return new self(
            typeCode: self::TYPE_RECTIFICATIVE,
            documentId: $documentId,
            issueDate: $issueDate,
            issueTime: $issueTime,
            timeZone: $timeZone,
            declarantParty: $declarantParty,
            paymentReport: $paymentReport,
        );
    }

    public static function combinedRectificativeReport(
        string $documentId,
        string $issueDate,
        string $issueTime,
        string $timeZone,
        TransactionReportData $transactionReport,
        PaymentReportData $paymentReport,
        ?DeclarantPartyData $declarantParty = null,
    ): self {
        return new self(
            typeCode: self::TYPE_RECTIFICATIVE,
            documentId: $documentId,
            issueDate: $issueDate,
            issueTime: $issueTime,
            timeZone: $timeZone,
            declarantParty: $declarantParty,
            transactionReport: $transactionReport,
            paymentReport: $paymentReport,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'schemaVersion' => $this->schemaVersion,
            'typeCode' => $this->typeCode,
            'documentId' => $this->documentId,
            'issueDate' => $this->issueDate,
            'issueTime' => $this->issueTime,
            'timeZone' => $this->timeZone,
            'declarantParty' => $this->declarantParty?->toArray(),
            'transactionReport' => $this->transactionReport?->toArray(),
            'paymentReport' => $this->paymentReport?->toArray(),
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
            throw new InvalidArgumentException('Unsupported France report schemaVersion.');
        }

        if (! in_array($this->typeCode, [self::TYPE_INITIAL, self::TYPE_RECTIFICATIVE], true)) {
            throw new InvalidArgumentException('typeCode must be IN or RE.');
        }

        ReportDataValidator::assertNonEmptyString($this->documentId, 'documentId');
        ReportDataValidator::assertDate($this->issueDate, 'issueDate');
        ReportDataValidator::assertTime($this->issueTime, 'issueTime');
        ReportDataValidator::assertNonEmptyString($this->timeZone, 'timeZone');

        if (is_null($this->transactionReport) && is_null($this->paymentReport)) {
            throw new InvalidArgumentException('At least one of transactionReport or paymentReport is required.');
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function upgradeToCurrentSchema(array $data): array
    {
        $schemaVersion = (int) ($data['schemaVersion'] ?? self::CURRENT_SCHEMA_VERSION);

        if ($schemaVersion > self::CURRENT_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported future France report schemaVersion.');
        }

        $data['schemaVersion'] = $schemaVersion;

        return $data;
    }
}
