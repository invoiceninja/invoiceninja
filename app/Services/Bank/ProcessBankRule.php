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
use App\Services\AbstractService;

class ProcessBankRule extends AbstractService
{

    public function __construct(private BankTransaction $bank_transaction, $rule){}

    public function run() : void
    {

    }
    
}