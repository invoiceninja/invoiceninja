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

use App\Models\BankIntegration;

class BankIntegrationFactory
{
    public static function create(int $company_id, int $user_id, int $account_id): BankIntegration
    {
        $bank_integration = new BankIntegration();
        $bank_integration->account_id = $account_id;
        $bank_integration->user_id = $user_id;
        $bank_integration->company_id = $company_id;

        $bank_integration->provider_name = '';
        $bank_integration->bank_account_id = '';
        $bank_integration->bank_account_name = '';
        $bank_integration->bank_account_number = '';
        $bank_integration->bank_account_status = '';
        $bank_integration->bank_account_type = '';
        $bank_integration->balance = 0;
        $bank_integration->currency = '';
        $bank_integration->auto_sync = 1;

        return $bank_integration;
    }
}
