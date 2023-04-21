<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */


namespace App\Jobs\Invoice;

use App\Utils\Ninja;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Invoice\EInvoice\FacturaEInvoice;
use App\Services\Invoice\EInvoice\ZugferdEInvoice;

class CreateEInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $deleteWhenMissingModels = true;

    public function __construct(private Invoice $invoice, private bool $alterPDF, private string $custom_pdf_path = "")
    {
    }

    /**
     * Execute the job.
     *
     *
     * @return string
     */
    public function handle(): string
    {
        /* Forget the singleton*/
        App::forgetInstance('translator');

        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->invoice->client->locale());

        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->invoice->client->getMergedSettings()));
        
        $e_invoice_type = $this->invoice->client->getSetting('e_invoice_type');
        
        switch ($e_invoice_type) {
            case "EN16931":
            case "XInvoice_2_2":
            case "XInvoice_2_1":
            case "XInvoice_2_0":
            case "XInvoice_1_0":
            case "XInvoice-Extended":
            case "XInvoice-BasicWL":
            case "XInvoice-Basic":
                return (new ZugferdEInvoice($this->invoice, $this->alterPDF, $this->custom_pdf_path))->run();
            case "Facturae_3.2":
            case "Facturae_3.2.1":
            case "Facturae_3.2.2":
                return (new FacturaEInvoice($this->invoice, str_replace("Facturae_", "", $e_invoice_type)))->run();
            default:
                return (new ZugferdEInvoice($this->invoice, $this->alterPDF, $this->custom_pdf_path))->run();
                break;

        }

    }
}
