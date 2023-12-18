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
        // multiDB environment, need to @turbo124 do we need any changes here for selfhosted non-multidb envs
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            if (Ninja::isHosted()) { // @turbo124 @todo I migrated the schedule for the job within the kernel to execute on all platforms and use the same expression here to determine if yodlee can run or not. Please chek/verify
                nlog("syncing transactions - yodlee");

                Account::with('bank_integrations')->whereNotNull('bank_integration_account_id')->cursor()->each(function ($account) {

                    if ($account->isPaid() && $account->plan == 'enterprise') {
                        $account->bank_integrations()->where('integration_type', BankIntegration::INTEGRATION_TYPE_YODLEE)->where('auto_sync', true)->where('is_deleted', false)->cursor()->each(function ($bank_integration) use ($account) {
                            (new ProcessBankTransactionsYodlee($account, $bank_integration))->handle();
                        });
                    }

                });
            }

            if (config("ninja.nordigen.secret_id") && config("ninja.nordigen.secret_key")) { // @turbo124 check condition, when to execute this should be placed here (isSelfHosted || isPro/isEnterprise)
                nlog("syncing transactions - nordigen");

                Account::with('bank_integrations')->cursor()->each(function ($account) {

                    if ((Ninja::isSelfHost() || (Ninja::isHosted() && $account->isPaid() && $account->plan == 'enterprise'))) {
                        $account->bank_integrations()->where('integration_type', BankIntegration::INTEGRATION_TYPE_NORDIGEN)->where('auto_sync', true)->where('is_deleted', false)->cursor()->each(function ($bank_integration) {
                            (new ProcessBankTransactionsNordigen($bank_integration))->handle();
                        });
                    }

                });
            }

            nlog("syncing transactions - done");
        }
    }
}
