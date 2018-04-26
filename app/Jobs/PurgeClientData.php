<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\Invoice;
use App\Models\LookupAccount;
use DB;
use Exception;
use App\Libraries\HistoryUtils;
use Utils;

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
        $user = auth()->user();
        $client = $this->client;
        $contact = $client->getPrimaryContact();

        if (! $user->is_admin) {
            return;
        }

        $message = sprintf('%s %s (%s) purged client: %s %s', date('Y-m-d h:i:s'), $user->email, request()->getClientIp(), $client->name, $contact->email);

        if (config('app.log') == 'single') {
            @file_put_contents(storage_path('logs/purged-clients.log'), $message, FILE_APPEND);
        } else {
            Utils::logError('[purged client] ' . $message);
        }

        $invoices = $client->invoices()->withTrashed()->get();
        $expenses = $client->expenses()->withTrashed()->get();

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
