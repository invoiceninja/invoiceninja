<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Helcim;

final class HelcimAchTransaction
{
    public function __construct(
        public readonly array $raw,
        public readonly ?string $transactionId,
        public readonly ?string $orderId,
        public readonly ?string $invoiceNumber,
        public readonly ?float $amount,
        public readonly ?string $currency,
        public readonly ?string $authorizationStatus,
        public readonly ?string $clearingStatus,
        public readonly ?string $status,
        public readonly ?string $bankToken,
        public readonly ?string $bankAccountId,
        public readonly ?string $customerId,
        public readonly ?string $customerCode,
        public readonly ?string $bankAccountType,
        public readonly ?string $bankAccountNumber,
    ) {
    }

    public static function from(array $response): self
    {
        $data = self::unwrap($response);

        return new self(
            raw: $data,
            transactionId: self::stringValue($data['transactionId'] ?? $data['transaction_id'] ?? $data['id'] ?? null),
            orderId: self::stringValue($data['orderId'] ?? $data['order_id'] ?? null),
            invoiceNumber: self::stringValue($data['invoiceNumber'] ?? $data['invoice_number'] ?? null),
            amount: self::floatValue($data['amount'] ?? $data['totalAmount'] ?? null),
            currency: self::currencyValue($data['currency'] ?? $data['currencyCode'] ?? $data['currencyId'] ?? null),
            authorizationStatus: self::authorizationStatus($data['statusAuth'] ?? $data['status_auth'] ?? null),
            clearingStatus: self::clearingStatus($data['statusClearing'] ?? $data['status_clearing'] ?? null),
            status: self::statusValue($data['status'] ?? $data['transactionStatus'] ?? null),
            bankToken: self::stringValue($data['bankToken'] ?? $data['bank_token'] ?? null),
            bankAccountId: self::stringValue($data['bankAccountId'] ?? $data['bank_account_id'] ?? null),
            customerId: self::stringValue($data['customerId'] ?? data_get($data, 'customer.id')),
            customerCode: self::stringValue($data['customerCode'] ?? data_get($data, 'customer.code')),
            bankAccountType: self::stringValue($data['bankAccountType'] ?? $data['account_type'] ?? null),
            bankAccountNumber: self::stringValue($data['bankAccountNumber'] ?? $data['account_number'] ?? null),
        );
    }

    public function isFailed(): bool
    {
        return in_array($this->authorizationStatus, ['DECLINED', 'CANCELLED'], true)
            || in_array($this->clearingStatus, ['REJECTED', 'RETURNED'], true)
            || in_array($this->status, ['FAILED', 'DECLINED', 'ERROR', 'REJECTED', 'RETURNED', 'NSF', 'VOIDED', 'CANCELLED'], true);
    }

    public function isCompleted(): bool
    {
        return $this->clearingStatus === 'CLEARED'
            || in_array($this->status, ['CLEARED', 'SETTLED', 'COMPLETED'], true);
    }

    public function isPending(): bool
    {
        if ($this->isFailed() || $this->isCompleted()) {
            return false;
        }

        return in_array($this->authorizationStatus, ['APPROVED', 'PENDING'], true)
            || in_array($this->clearingStatus, ['OPENED', 'CONTESTED'], true)
            || in_array($this->status, ['APPROVED', 'PENDING', 'IN_PROGRESS', 'QUEUED', 'SUBMITTED', 'OPENED', 'SUCCESS'], true);
    }

    public function isAccepted(): bool
    {
        return $this->isCompleted() || $this->isPending();
    }

    public function statusDescription(): string
    {
        return $this->clearingStatus
            ?? $this->authorizationStatus
            ?? $this->status
            ?? 'UNKNOWN';
    }

    private static function unwrap(array $response): array
    {
        $data = $response;

        foreach (['data', 'transaction'] as $key) {
            while (isset($data[$key]) && is_array($data[$key])) {
                $data = $data[$key];
            }
        }

        return $data;
    }

    private static function authorizationStatus(mixed $status): ?string
    {
        return match ((string) $status) {
            '1' => 'APPROVED',
            '2' => 'DECLINED',
            '4' => 'CANCELLED',
            '5' => 'PENDING',
            default => self::statusValue($status),
        };
    }

    private static function clearingStatus(mixed $status): ?string
    {
        return match ((string) $status) {
            '0' => 'OPENED',
            '1' => 'CLEARED',
            '4' => 'REJECTED',
            default => self::statusValue($status),
        };
    }

    private static function currencyValue(mixed $currency): ?string
    {
        return match ((string) $currency) {
            '1' => 'CAD',
            '2' => 'USD',
            default => self::statusValue($currency),
        };
    }

    private static function statusValue(mixed $value): ?string
    {
        $value = self::stringValue($value);

        return $value === null ? null : strtoupper($value);
    }

    private static function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function floatValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
