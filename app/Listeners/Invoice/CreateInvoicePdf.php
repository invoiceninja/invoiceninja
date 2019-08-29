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

namespace App\Listeners\Invoice;

use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class CreateInvoicePdf
{
    protected $activity_repo;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {

        $invoice = $event->invoice;
        $path = $invoice->client->client_hash . '/invoices/'; 
        $file = $path . $invoice->invoice_number . '.pdf';
        //get invoice template

        $html = $this->generateInvoiceHtml($invoice->settings->template, $invoice)

        //todo - move this to the client creation stage so we don't keep hitting this unnecessarily
        Storage::makeDirectory('public/' . $path, 0755);

        //create pdf
        $this->makePdf(null,null,$html,$file)
        
        //store pdf
        //$path = Storage::putFile('public/' . $path, $this->file);
        //$url = Storage::url($path);
    }


    private function makePdf($header, $footer, $html, $pdf)
    {
            Browsershot::html($html)
            //->showBrowserHeaderAndFooter()
            //->headerHtml($header)
            //->footerHtml($footer)
            ->waitUntilNetworkIdle()
            //->margins(10,10,10,10)
            ->savePdf($pdf);
    }

    private function generateInvoiceHtml($template, $invoice)
    {

    }
}
