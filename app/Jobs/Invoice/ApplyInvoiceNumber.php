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

use App\Libraries\MultiDB;
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

    private $company;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, $settings, $company)
    {

        $this->invoice = $invoice;

        $this->settings = $settings;

        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * 
     * @return void
     */
    public function handle()
    {

        MultiDB::setDB($this->company->db);


        //return early
        if($this->invoice->number != '')
            return $this->invoice;

        switch ($this->settings->counter_number_applied) {
            case 'when_saved':
                $this->invoice->number = $this->getNextInvoiceNumber($this->invoice->client);
                break;
            case 'when_sent':
                if($this->invoice->status_id == Invoice::STATUS_SENT)
                    $this->invoice->number = $this->getNextInvoiceNumber($this->invoice->client);
                break;
            
            default:
                # code...
                break;
        }
   
        $this->invoice->save();
            
        return $this->invoice;

    }


}
