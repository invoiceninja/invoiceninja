<?php

namespace App\Services\Invoice;

use App\Models\ClientContact;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

class MergeEInvoice
{

    /**
     * @param Invoice $invoice
     * @param mixed|null $contact
     */
    public function __construct(public Invoice $invoice, public ?ClientContact $contact = null)
    {
    }

    public function run(): void
    {
        $file_path_xml = $this->invoice->client->e_invoice_filepath($this->invoice->invitations->first()). $this->invoice->getFileName("xml");
        $file_path_pdf = $this->invoice->getFileName();
        // $disk = 'public';
        $disk = config('filesystems.default');

        $file_xml = Storage::disk($disk)->exists($file_path_xml);
        $file_pdf = Storage::disk($disk)->exists($file_path_pdf);

        if ($file_xml && $file_pdf) {
            (new \App\Jobs\Invoice\MergeEInvoice($this->invoice))->handle();
        }

    }
}
