<?php

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use App\Models\Product;
use App\Services\Invoice\EInvoice\FacturaEInvoice;
use App\Services\Invoice\EInvoice\ZugferdEInvoice;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;


class CreateXInvoice implements ShouldQueue
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

        switch ($this->invoice->client->getSetting('e_invoice_type')) {
            case "EN16931":
            case "XInvoice_2_2":
            case "XInvoice_2_1":
            case "XInvoice_2_0":
            case "XInvoice_1_0":
            case "XInvoice-Extended":
            case "XInvoice-BasicWL":
            case "XInvoice-Basic":
                return (new ZugferdEInvoice($this->invoice, $this->alterPDF, $this->custom_pdf_path))->run();
            case "Facturae_3_2_2":
                return (new FacturaEInvoice($this->invoice))->run();
            default:
                return (new ZugferdEInvoice($this->invoice, $this->alterPDF, $this->custom_pdf_path))->run();
                break;

        }
    }
}
