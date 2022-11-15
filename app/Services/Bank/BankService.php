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

namespace App\Services\Bank;

use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Services\Bank\ProcessBankRule;

class BankService
{

    public function __construct(public BankTransaction $bank_transaction) {}


    public function matchInvoiceNumber()
    {

        if(strlen($this->bank_transaction->description) > 1)
        {

            $i = Invoice::where('company_id', $this->bank_transaction->company_id)
                    ->whereIn('status_id', [1,2,3])
                    ->where('is_deleted', 0)
                    ->where('number', 'LIKE', '%'.$this->bank_transaction->description.'%')
                    ->first();

            return $i ?: false;
        }

        return false;

    }

    public function processRule($rule)
    {
        (new ProcessBankRule($this->bank_transaction, $rule))->run();

        return $this;
    }

}