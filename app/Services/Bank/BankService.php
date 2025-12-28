<?php

/**
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

    public function processRules()
    {
        (new ProcessBankRules($this->bank_transaction))->run();
    }
}
