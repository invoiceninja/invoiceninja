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
        $file_path = $this->invoice->client->e_invoice_filepath($this->invoice->invitations->first()). $this->invoice->getFileName("xml");

        // $disk = 'public';
        $disk = config('filesystems.default');

        $file = Storage::disk($disk)->exists($file_path);

        if (! $file) {
            (new \App\Jobs\Invoice\MergeEInvoice($this->invoice))->handle();
        }

    }
}
