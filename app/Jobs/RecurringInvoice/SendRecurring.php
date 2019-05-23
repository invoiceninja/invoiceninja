<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\RecurringInvoice;

use App\Factory\RecurringInvoiceToInvoiceFactory;
use App\Models\RecurringInvoice;
use App\Utils\Traits\GeneratesNumberCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendRecurring
{

    use GeneratesNumberCounter;

    public $recurring_invoice;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(RecurringInvoice $recurring_invoice)
    {

        $this->recurring_invoice = $recurring_invoice;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {

        // Generate Standard Invoice
        $invoice = RecurringInvoiceToInvoiceFactory::create($this->recurring_invoice);
        $invoice->invoice_number = $this->getNextNumber($invoice);
        $invoice->save();

        // Queue: Emails for invoice
        

        // Calcuate next send date for recurring invoice
         
        
        // Decrement # of invoices remaining 
         

        // Fire Payment if auto-bill is enabled
        

        // Clean up recurring invoice object
        
        $this->recurring_invoice->remaining_cycles = $this->recurring_invoice->remainingCycles();
        $this->recurring_invoice->last_sent_date = date('Y-m-d');

        if($this->recurring_invoice->remaining_cycles != 0)
            $this->recurring_invoice->next_send_date = $this->recurring_invoice->nextSendDate();
        else 
            $this->recurring_invoice->setCompleted();
        

        $this->recurring_invoice->save(); 

    }


 
           
    

}
