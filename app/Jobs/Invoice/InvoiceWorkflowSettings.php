<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Invoice;

use App\Models\Client;
use App\Models\Invoice;
use App\Repositories\BaseRepository;
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

    private $base_repository;

    /**
     * Create a new job instance.
     *
     * @param Invoice $invoice
     * @param Client|null $client
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
        $this->base_repository = new BaseRepository();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->client = $this->invoice->client;

        if ($this->client->getSetting('auto_archive_invoice')) {
            /* Throws: Payment amount xxx does not match invoice totals. */
            $this->base_repository->archive($this->invoice);
        }

        //@TODO this setting should only fire for recurring invoices
        // if ($this->client->getSetting('auto_email_invoice')) {
        //    $this->invoice->invitations->each(function ($invitation, $key) {
        //         $this->invoice->service()->sendEmail($invitation->contact);
        //    });
        // }
    }
}
