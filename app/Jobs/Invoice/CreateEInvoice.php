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

use App\Models\Invoice;
use App\Services\Invoice\EInvoice\FacturaEInvoice;
use App\Services\Invoice\EInvoice\ZugferdEInvoice;
use App\Utils\Ninja;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class CreateEInvoice implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public function __construct(private Invoice $invoice, private bool $returnObject = false)
    {
    }

    /**
     * Execute the job.
     *
     * @return string|ZugferdDocumentBuilder
     */
    public function handle(): string|ZugferdDocumentBuilder
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
            case "XInvoice_3_0":
            case "XInvoice_2_3":
            case "XInvoice_2_2":
            case "XInvoice_2_1":
            case "XInvoice_2_0":
            case "XInvoice_1_0":
            case "XInvoice-Extended":
            case "XInvoice-BasicWL":
            case "XInvoice-Basic":
                $zugferd = (new ZugferdEInvoice($this->invoice))->run();

                return $this->returnObject ? $zugferd->xrechnung : $zugferd->getXml();
            case "Facturae_3.2":
            case "Facturae_3.2.1":
            case "Facturae_3.2.2":
                return (new FacturaEInvoice($this->invoice, str_replace("Facturae_", "", $e_invoice_type)))->run();
            default:

                $zugferd = (new ZugferdEInvoice($this->invoice))->run();

                return $this->returnObject ? $zugferd : $zugferd->getXml();

        }

    }
}
