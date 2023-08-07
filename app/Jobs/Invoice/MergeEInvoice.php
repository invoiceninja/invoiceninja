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
    public function __construct(public Invoice $invoice, public ?ClientContact $contact = null)
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
        $filepath_pdf = $this->invoice->client->invoice_filepath($this->invoice->invitations->first()) . $this->invoice->getFileName();
        $e_invoice_path = $this->invoice->client->e_invoice_filepath($this->invoice->invitations->first()) . $this->invoice->getFileName("xml");
        $document = ZugferdDocumentReader::readAndGuessFromFile($e_invoice_path);
        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($this->invoice->client->e_invoice_filepath($this->invoice->invitations->first()))) {
            Storage::makeDirectory($this->invoice->client->e_invoice_filepath($this->invoice->invitations->first()));
        }
        $pdfBuilder = new ZugferdDocumentPdfBuilder($document, Storage::disk($disk)->path($filepath_pdf));
        $pdfBuilder->generateDocument();
        $pdfBuilder->saveDocument(Storage::disk($disk)->path($filepath_pdf));
    }
}
