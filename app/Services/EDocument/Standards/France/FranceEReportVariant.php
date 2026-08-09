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

namespace App\Services\EDocument\Standards\France;

use App\DataMapper\FranceEReporting\FRReportData;
use App\Models\TransactionEvent;

enum FranceEReportVariant: string
{
    case TransactionInitial = 'transaction_in';
    case PaymentInitial = 'payment_in';
    case PaymentRectificative = 'payment_re';

    public function typeCode(): string
    {
        return match ($this) {
            self::TransactionInitial, self::PaymentInitial => FRReportData::TYPE_INITIAL,
            self::PaymentRectificative => FRReportData::TYPE_RECTIFICATIVE,
        };
    }

    public function isTransaction(): bool
    {
        return $this === self::TransactionInitial;
    }

    public function isStorecoveQualified(): bool
    {
        return in_array(
            $this->value,
            config('ninja.france_reporting_storecove_qualified_variants', []),
            true,
        );
    }

    /**
     * @return array<int, int>
     */
    public function sourceEventIds(): array
    {
        return match ($this) {
            self::TransactionInitial => [
                TransactionEvent::FR_B2C_TRANSACTION,
                TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION,
            ],
            self::PaymentInitial, self::PaymentRectificative => [
                TransactionEvent::FR_B2C_PAYMENT,
                TransactionEvent::FR_VAT_EXCLUDED_PAYMENT,
            ],
        };
    }
}
