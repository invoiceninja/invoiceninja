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

namespace App\Jobs\Ledger;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LedgerBalanceUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle() :void
    {
        nlog('Updating company ledgers');

        if (! config('ninja.db.multi_db_enabled')) {
            $this->checkLedger();
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->checkLedger();
            }
        }

        nlog('Finished checking company ledgers');
    }

    public function checkLedger()
    {
        nlog('Checking ledgers....');

        CompanyLedger::where('balance', 0)->where('adjustment', '!=', 0)->cursor()->each(function ($company_ledger) {
            if ($company_ledger->balance > 0) {
                return;
            }

            $last_record = CompanyLedger::where('client_id', $company_ledger->client_id)
                            ->where('company_id', $company_ledger->company_id)
                            ->where('balance', '!=', 0)
                            ->orderBy('id', 'DESC')
                            ->first();

            if (! $last_record) {
                return;
            }

            nlog('Updating Balance NOW');

            $company_ledger->balance = $last_record->balance + $company_ledger->adjustment;
            $company_ledger->save();
        });
    }
}
