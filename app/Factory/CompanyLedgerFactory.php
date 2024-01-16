<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\CompanyLedger;

class CompanyLedgerFactory
{
    public static function create(int $company_id, int $user_id): CompanyLedger
    {
        $company_ledger = new CompanyLedger();
        $company_ledger->company_id = $company_id;
        $company_ledger->user_id = $user_id;
        $company_ledger->adjustment = 0;
        $company_ledger->balance = 0;
        $company_ledger->notes = '';
        $company_ledger->hash = '';
        $company_ledger->client_id = 0;

        return $company_ledger;
    }
}
