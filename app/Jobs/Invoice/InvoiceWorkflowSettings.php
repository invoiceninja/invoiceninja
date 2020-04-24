<?php

namespace App\Jobs\Invoice;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InvoiceWorkflowSettings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;
    public $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, Client $client = null)
    {
        $this->invoice = $invoice;
        $this->client = $client ?? $invoice->client;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->client->getSetting('auto_archive_invoice')) {
            $this->invoice->archive();
        }

        if ($this->client->getSetting('auto_email_invoice')) {
           // .. Send e-mail with invoice.
        }
    }
}
