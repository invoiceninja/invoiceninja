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

use App\Models\BankTransactionRule;

/**
 * Class for bank transaction rule repository.
 */
class BankTransactionRuleRepository extends BaseRepository
{
    public function save($data, BankTransactionRule $bank_transaction_rule)
    {
        $bank_transaction_rule->fill($data);

        $bank_transaction_rule->save();

        return $bank_transaction_rule;
    }
}
