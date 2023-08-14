<?php

namespace App\Services\Invoice;

use App\Models\ClientContact;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

class MergeEInvoice
{

    /**
     * @param Invoice $invoice
     */
    public function __construct(public Invoice $invoice, public string $pdf_path = "")
    {
    }

    public function run(): void
    {
        if (!empty($this->pdf_path)) {
            (new \App\Jobs\Invoice\MergeEInvoice($this->invoice, $this->pdf_path))->handle();
        }
        else {
            (new \App\Jobs\Invoice\MergeEInvoice($this->invoice))->handle();
        }

    }
}
