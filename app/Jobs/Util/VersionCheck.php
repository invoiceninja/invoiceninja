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

namespace App\Jobs\Util;

use App\Models\Account;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class VersionCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $version_file = trim(@file_get_contents(config('ninja.version_url')));

        nlog("latest version = {$version_file}");

        if (Ninja::isSelfHost() && $version_file) {
            Account::whereNotNull('id')->update(['latest_version' => $version_file]);
        }

        if(Ninja::isSelfHost())
        {
            $account = Account::first();

            if(!$account)
                return;

            if($account->plan == 'white_label' && $account->plan_expires && Carbon::parse($account->plan_expires)->lt(now())){
                $account->plan = null;
                $account->plan_expires = null;
                $account->save();
            }
        }
    }
}
