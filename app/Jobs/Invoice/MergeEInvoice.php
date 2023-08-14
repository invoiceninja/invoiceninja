<?php

namespace App\Jobs\Invoice;

use App\Models\ClientContact;
use App\Models\Invoice;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use horstoeko\zugferd\ZugferdDocumentReader;

class MergeEInvoice implements ShouldQueue
{
    public function __construct(public Invoice $invoice, private string $pdf_path = "")
    {
    }

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
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
                $this->embedEInvoiceZuGFerD();
            //case "Facturae_3.2":
            //case "Facturae_3.2.1":
            //case "Facturae_3.2.2":
            //
            default:
                $this->embedEInvoiceZuGFerD();
                break;
        }
    }

    /**
     * @throws \Exception
     */
    private function embedEInvoiceZuGFerD(): void
    {
        $filepath_pdf = !empty($this->pdf_path) ? $this->pdf_path : $this->invoice->service()->getInvoicePdf();
        $disk = config('filesystems.default');
        $e_rechnung = (new CreateEInvoice($this->invoice, true))->handle();
        if (!empty($this->pdf_path)){
            $realpath_pdf = $filepath_pdf;
        }
        else {
           $realpath_pdf = Storage::disk($disk)->path($filepath_pdf);
        }
        $pdfBuilder = new ZugferdDocumentPdfBuilder($e_rechnung, $realpath_pdf);
        $pdfBuilder->generateDocument();
        $pdfBuilder->saveDocument($realpath_pdf);
    }
}
