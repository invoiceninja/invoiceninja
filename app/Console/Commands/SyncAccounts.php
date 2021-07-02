<?php

namespace App\Console\Commands;

use App\Libraries\Utils;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SyncAccounts extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:sync-v5';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync accounts to v5 - (Hosted function only)';
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        if(!Utils::isNinjaProd())
            return;

        config(['database.default' => DB_NINJA_1]);

        $this->updateAccounts();

        config(['database.default' => DB_NINJA_2]);

        $this->updateAccounts();

    }

    private function updateAccounts()
    {
        $data = [];

        $a = Company::whereIn('plan', ['pro', 'enterprise'])
                            ->with('accounts')
                            ->cursor()->each(function ($company) use ($data){

                                $accounts = $company->accounts->pluck('account_key');

                                $data[] = [
                                    'plan' => $company->plan,
                                    'plan_term' => $company->plan_term,
                                    'plan_started' => $company->plan_started,
                                    'plan_paid' => $company->plan_paid,
                                    'plan_expires' => $company->plan_expires,
                                    'num_users' => $company->num_users,
                                    'accounts' => $accounts
                                ];

                            });

        //post DATA
    }
}