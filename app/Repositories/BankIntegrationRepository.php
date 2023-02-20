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

namespace App\Repositories;

use App\Models\BankIntegration;

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
}
