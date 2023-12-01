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

use App\Jobs\Bank\ProcessBankTransactionsYodlee;
use App\Jobs\Bank\ProcessBankTransactionsNordigen;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\BankIntegration;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

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

            nlog("syncing transactions - yodlee");

            Account::with('bank_integrations')->whereNotNull('bank_integration_yodlee_account_id')->cursor()->each(function ($account) {

                if ($account->isPaid() && $account->plan == 'enterprise') {

                    $account->bank_integrations()->where('integration_type', BankIntegration::INTEGRATION_TYPE_YODLEE)->andWhere('auto_sync', true)->cursor()->each(function ($bank_integration) use ($account) {

                        (new ProcessBankTransactionsYodlee($account, $bank_integration))->handle();

                    });

                }

            });

            nlog("syncing transactions - nordigen");

            Account::with('bank_integrations')->whereNotNull('bank_integration_nordigen_client_id')->andWhereNotNull('bank_integration_nordigen_client_secret')->cursor()->each(function ($account) {

                $account->bank_integrations()->where('integration_type', BankIntegration::INTEGRATION_TYPE_NORDIGEN)->andWhere('auto_sync', true)->cursor()->each(function ($bank_integration) use ($account) {

                    (new ProcessBankTransactionsNordigen($account, $bank_integration))->handle();

                });

            });

            nlog("syncing transactions - done");
        }
    }

}
