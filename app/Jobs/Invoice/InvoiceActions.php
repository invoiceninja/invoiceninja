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
use App\Repositories\InvoiceRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceActions
 * @package App\Jobs\Invoice
 */
class InvoiceActions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Invoice $invoice
     */
    public $invoice;

    /**
     * @var array $data
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, array $data)
    {

        $this->invoice = $invoice;

        $this->data = $data;

    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        switch($this->data['action'])
        {
            //fire actions here
        }

    }
}
