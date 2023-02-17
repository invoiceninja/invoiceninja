<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Ninja;

use App\Jobs\Bank\ProcessBankTransactions;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BankTransactionSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //multiDB environment, need to
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            nlog("syncing transactions");

            $a = Account::with('bank_integrations')->whereNotNull('bank_integration_account_id')->cursor()->each(function ($account) {
                // $queue = Ninja::isHosted() ? 'bank' : 'default';

                if ($account->isPaid() && $account->plan == 'enterprise') {
                    $account->bank_integrations()->where('auto_sync', true)->cursor()->each(function ($bank_integration) use ($account) {
                        (new ProcessBankTransactions($account->bank_integration_account_id, $bank_integration))->handle();
                    });
                }
            });
        }
    }
}
