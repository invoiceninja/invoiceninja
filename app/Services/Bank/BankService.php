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

namespace App\Services\Bank;

use App\Models\BankTransaction;
use App\Models\Invoice;

class BankService
{
    public function __construct(public BankTransaction $bank_transaction)
    {
    }


    public function matchInvoiceNumber()
    {
        if (strlen($this->bank_transaction->description) > 1) {
            $i = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
                    ->whereIn('status_id', [1,2,3])
                    ->where('is_deleted', 0)
                    ->where('number', 'LIKE', '%'.$this->bank_transaction->description.'%')
                    ->first();

            return $i ?: false;
        }

        return false;
    }

    public function processRules()
    {
        (new ProcessBankRules($this->bank_transaction))->run();
    }
}
