<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\EDocument;

use App\Utils\Ninja;
use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public function __construct(private object $document, private bool $returnObject = false)
    {
    }

    /**
     * Execute the job.
     *
     * @return string|ZugferdDocumentBuilder
     */
    public function handle(): string|ZugferdDocumentBuilder
    {

        if ($this->document instanceof Invoice) {
            switch ($e_document_type) {
                case "FACT1":
                    return (new RoEInvoice($this->document))->generateXml();
                case "FatturaPA":
                    return (new FatturaPA($this->document))->run();
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
                    $zugferd = (new ZugferdEDokument($this->document))->run();

                    return $this->returnObject ? $zugferd->xdocument : $zugferd->getXml();
                case "Facturae_3.2":
                case "Facturae_3.2.1":
                case "Facturae_3.2.2":
                    return (new FacturaEInvoice($this->document, str_replace("Facturae_", "", $e_document_type)))->run();
                default:

                    $zugferd = (new ZugferdEDokument($this->document))->run();

                    return $this->returnObject ? $zugferd : $zugferd->getXml();
        return "";
            }
        }
    }
}
