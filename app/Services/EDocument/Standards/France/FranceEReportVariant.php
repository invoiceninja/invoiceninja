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

enum FranceEReportVariant: string
{
    case TransactionInitial = 'transaction_in';
    case TransactionRectificative = 'transaction_re';
    case PaymentInitial = 'payment_in';
    case PaymentRectificative = 'payment_re';

    public function typeCode(): string
    {
        return match ($this) {
            self::TransactionInitial, self::PaymentInitial => FRReportData::TYPE_INITIAL,
            self::TransactionRectificative, self::PaymentRectificative => FRReportData::TYPE_RECTIFICATIVE,
        };
    }

    public function isTransaction(): bool
    {
        return in_array($this, [self::TransactionInitial, self::TransactionRectificative], true);
    }

    public function isRectificative(): bool
    {
        return in_array($this, [self::TransactionRectificative, self::PaymentRectificative], true);
    }
}
