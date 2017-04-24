<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\Document;
use Auth;
use DB;
use Exception;

class PurgeAccountData extends Job
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = Auth::user();
        $account = $user->account;

        if (! $user->is_admin) {
            throw new Exception(trans('texts.forbidden'));
        }

        // delete the documents from cloud storage
        Document::scope()->each(function ($item, $key) {
            $item->delete();
        });

        $tables = [
            'activities',
            'invitations',
            'account_gateway_tokens',
            'payment_methods',
            'credits',
            'expense_categories',
            'expenses',
            'invoice_items',
            'payments',
            'invoices',
            'tasks',
            'projects',
            'products',
            'vendor_contacts',
            'vendors',
            'contacts',
            'clients',
        ];

        foreach ($tables as $table) {
            DB::table($table)->where('account_id', '=', $user->account_id)->delete();
        }

        $account->invoice_number_counter = 1;
        $account->quote_number_counter = 1;
        $account->client_number_counter = 1;
        $account->save();
    }
}
