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

use App\Jobs\Bank\MatchBankTransactions;
use App\Models\BankTransaction;

/**
 * Class for bank transaction repository.
 */
class BankTransactionRepository extends BaseRepository
{
    public function save($data, BankTransaction $bank_transaction)
    {
        if (array_key_exists('bank_integration_id', $data)) {
            $bank_transaction->bank_integration_id = $data['bank_integration_id'];
        }

        $bank_transaction->fill($data);
        $bank_transaction->save();

        $bank_transaction->service()->processRules();

        return $bank_transaction->fresh();
    }

    public function convert_matched($bank_transactions)
    {
        $data['transactions'] = $bank_transactions->map(function ($bt) {
            return ['id' => $bt->id, 'invoice_ids' => $bt->invoice_ids, 'ninja_category_id' => $bt->ninja_category_id];
        })->toArray();

        $bts = (new MatchBankTransactions(auth()->user()->company()->id, auth()->user()->company()->db, $data))->handle();
    }
}
