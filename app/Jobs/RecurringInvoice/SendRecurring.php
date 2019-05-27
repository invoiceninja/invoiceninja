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
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendRecurring
{

    use GeneratesCounter;
    
    public $recurring_invoice;

    protected $db;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(RecurringInvoice $recurring_invoice, string $db = 'db-ninja-01')
    {

        $this->recurring_invoice = $recurring_invoice;
        $this->db = $db;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {
        MultiDb::setDb($this->db);

        // Generate Standard Invoice
        $invoice = RecurringInvoiceToInvoiceFactory::create($this->recurring_invoice);
        $invoice->invoice_number = $this->getNextInvoiceNumber($this->recurring_invoice->client);
        $invoice->status_id = Invoice::STATUS_SENT;
        $invoice->save();

        // Queue: Emails for invoice  
        // $this->recurring_invoice->settings->invoice_email_list //todo comma separated list of emails to fire this email to                

        // Fire Payment if auto-bill is enabled
        if($this->recurring_invoice->settings->auto_bill)
            //PAYMENT ACTION HERE TODO

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
