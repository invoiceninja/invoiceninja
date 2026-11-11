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

enum FranceReportingStatus: int
{
    case Pending = 1;
    case Sent = 2;
    case Accepted = 3;
    case Rejected = 4;
    case RetryableFailure = 5;

    /**
     * @return array<int, int>
     */
    public static function openValues(): array
    {
        return [
            self::Pending->value,
            self::Sent->value,
            self::RetryableFailure->value,
        ];
    }
}
