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

namespace App\Repositories;

use App\Helpers\Bank\Yodlee\Yodlee;
use App\Models\BankIntegration;
use App\Utils\Ninja;

/**
 * Class for bank integration repository.
 */
class BankIntegrationRepository extends BaseRepository
{
    public function save($data, BankIntegration $bank_integration)
    {
        //stub to store
        $bank_integration->fill($data);

        $bank_integration->save();

        return $bank_integration->fresh();
    }

    /**
    * Removes the bank integration from Yodlee
    *
    * @param BankIntegration $bank_integration
    *
    * @return BankIntegration $bank_integration
    */
    public function delete($bank_integration): BankIntegration
    {
        if ($bank_integration->is_deleted) {
            return $bank_integration;
        }

        if (Ninja::isHosted() && $bank_integration->integration_type === BankIntegration::INTEGRATION_TYPE_YODLEE) {

            $account = $bank_integration->account;

            $bank_integration_account_id = $account->bank_integration_account_id;

            $yodlee = new Yodlee($bank_integration_account_id);

            $still_referenced = BankIntegration::query()
                                                ->where('account_id', $bank_integration->account_id)
                                                ->where('bank_account_id', $bank_integration->bank_account_id)
                                                ->where('id', '!=', $bank_integration->id)
                                                ->where('is_deleted', 0)
                                                ->exists();

            if(!$still_referenced) {
                try {
                    $yodlee->deleteAccount($bank_integration->bank_account_id);
                } catch (\Throwable $e) {

                    nlog("YODLEE: DELETE: {$e->getMessage()}");
                    return $bank_integration;
                }
            }

        }

        parent::delete($bank_integration);

        return $bank_integration;
    }

}
