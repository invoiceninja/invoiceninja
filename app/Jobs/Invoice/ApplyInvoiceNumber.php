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

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ApplyInvoiceNumber implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, GeneratesCounter;

    public $invoice;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, $settings)
    {

        $this->invoice = $invoice;

        $this->settings = $settings;
    }

    /**
     * Execute the job.
     *
     * 
     * @return void
     */
    public function handle()
    {
        //return early
        if($this->invoice->invoice_number != '')
            return $this->invoice;

        switch ($this->settings->counter_number_applied) {
            case 'when_saved':
                $this->invoice->invoice_number = $this->getNextInvoiceNumber($this->invoice->client);
                break;
            case 'when_sent':
                if($this->invoice->status_id == Invoice::STATUS_SENT)
                    $this->invoice->invoice_number = $this->getNextInvoiceNumber($this->invoice->client);
                break;
            
            default:
                # code...
                break;
        }
   
        $this->invoice->save();
            
        return $this->invoice;

    }


}
