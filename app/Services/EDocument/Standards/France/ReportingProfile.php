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

enum ReportingProfile: string
{
    case TenDay = 'ten_days';      // transaction reporting: 1-10, 11-20, 21-EOM
    case Monthly = 'monthly';    // 1st-EOM
    case BiMonthly = 'bi_monthly'; // Jan-Feb, Mar-Apr, etc.
}
