<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\BankTransaction;
use App\Models\Task;
use App\Models\TaskStatus;

/**
 * Class for bank transaction repository.
 */
class BankTransactionRepository extends BaseRepository
{

    public function save($data, BankTransaction $bank_transaction)
    {

        if(!isset($bank_transaction->bank_integration_id) && array_key_exists('bank_integration_id', $data))
            $bank_transaction->bank_integration_id = $data['bank_integration_id'];

        $bank_transaction->fill($data);

        $bank_transaction->save();

        return $bank_transaction;
    }

}
