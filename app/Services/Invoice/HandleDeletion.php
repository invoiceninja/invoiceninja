<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;

class HandleDeletion extends AbstractService
{
    use GeneratesCounter;


    private $invoice;

    public function __construct(Invoice $invoice)
    {        
        $this->invoice = $invoice;
    }

    public function run()
    {
        $balance_remainig = $this->invoice->balance;
        $total_paid = $this->invoice->amount - $this->invoice->balance;

        //change invoice status
        
        //set invoice balance to 0
        
        //decrease client balance by $total_paid 
    
        //remove paymentables from payment
    
        //decreate client paid_to_date by $total_paid

        //generate credit for the $total paid
           
    }
}
