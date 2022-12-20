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

namespace App\Jobs\Subscription;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanStaleInvoiceOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     *
     * @param int invoice_id
     * @param string $db
     */
    public function __construct(private int $invoice_id, private string $db){}

    /**
     * @param InvoiceRepository $repo 
     * @return void 
     */
    public function handle(InvoiceRepository $repo) : void
    {
        MultiDB::setDb($this->db);

        $invoice = Invoice::withTrashed()->find($this->invoice_id);

        if($invoice->is_proforma){
            $invoice->is_proforma = false;
            $repo->delete($invoice);
        }

    }

    public function failed($exception = null)
    {
    }
}
