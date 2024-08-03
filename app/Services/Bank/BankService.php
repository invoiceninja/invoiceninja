<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
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
