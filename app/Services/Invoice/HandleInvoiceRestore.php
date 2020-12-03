<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Services\AbstractService;
use Illuminate\Support\Facades\DB;

class HandleInvoiceRestore extends AbstractService
{

    private $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {

    	//determine whether we need to un-delete payments OR just modify the payment amount /applied balances.
    	
    	//adjust ledger balance
    	
    	//adjust paid to dates

    	//restore paymentables
    	
    }

}

