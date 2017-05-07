<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\Document;
use App\Models\LookupAccount;
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
        $account->client_number_counter = $account->client_number_counter > 0 ? 1 : 0;
        $account->save();

        if (env('MULTI_DB_ENABLED')) {
            $current = config('database.default');
            config(['database.default' => DB_NINJA_LOOKUP]);

            $lookupAccount = LookupAccount::whereAccountKey($account->account_key)->firstOrFail();
            DB::table('lookup_contacts')->where('lookup_account_id', '=', $lookupAccount->id)->delete();
            DB::table('lookup_invitations')->where('lookup_account_id', '=', $lookupAccount->id)->delete();

            config(['database.default' => $current]);
        }
    }
}
