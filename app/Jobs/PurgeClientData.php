<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\Invoice;
use App\Models\LookupAccount;
use DB;
use Exception;
use App\Libraries\HistoryUtils;

class PurgeClientData extends Job
{
    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $invoices = $this->client->invoices()->withTrashed()->get();
        $expenses = $this->client->expenses()->withTrashed()->get();

        foreach ($invoices as $invoice) {
            foreach ($invoice->documents as $document) {
                $document->delete();
            }
        }
        foreach ($expenses as $expense) {
            foreach ($expense->documents as $document) {
                $document->delete();
            }
        }

        $this->client->forceDelete();

        HistoryUtils::deleteHistory($this->client);
    }
}
