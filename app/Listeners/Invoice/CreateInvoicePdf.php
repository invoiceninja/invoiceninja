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
use Illuminate\Support\Facades\Log;
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
        $path = 'public/' . $invoice->client->client_hash . '/invoices/'; 
        $file = $path . $invoice->invoice_number . '.pdf';

        Log::error($file);
        //get invoice template

        $html = $this->generateInvoiceHtml($invoice->design(), $invoice);

        //todo - move this to the client creation stage so we don't keep hitting this unnecessarily
        Storage::makeDirectory($path, 0755);

        //create pdf
        $pdf = $this->makePdf(null,null,$html, $file);
        
       // $path = Storage::putFile($file, $pdf);

        //store pdf
        //$path = Storage::putFile('public/' . $path, $this->file);
        //$url = Storage::url($path);
    }


    private function makePdf($header, $footer, $html, $pdf) 
    {
            return Browsershot::html($html)
            //->showBrowserHeaderAndFooter()
            //->headerHtml($header)
            //->footerHtml($footer)
            ->waitUntilNetworkIdle()
            //->margins(10,10,10,10)
            ->savePdf('test.pdf');
    }

    /**
     * Generate the HTML invoice parsing variables 
     * and generating the final invoice HTML
     *     
     * @param  string $design either the path to the design template, OR the full design template string
     * @param  Collection $invoice  The invoice object
     * @return string           The invoice string in HTML format
     */
    private function generateInvoiceHtml($design, $invoice) :string
    {
        //swap labels
        $data['invoice'] = $invoice;

        return view($design, $data)->render();

        //return view('pdf.stub', $html)->render();
    }
}
