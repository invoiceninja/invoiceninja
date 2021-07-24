<?php

namespace App\Console\Commands;

use App\Jobs\HostedMigration;
use App\Libraries\Utils;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class ForceMigration extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:force-migrate-v5';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force migrate accounts to v5 - (Hosted function only)';
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
        return;

        if(!Utils::isNinjaProd())
            return;

        config(['database.default' => DB_NINJA_1]);

        $this->forceMigrate();

    }

    private function forceMigrate()
    {
        $data = [];

        $company = Company::where('plan', 'free')
                            ->where('is_migrated', false)
                            ->with('accounts')
                            ->first();

        $user = $company->accounts->first()->users()->whereNull('public_id')->orWhere('public_id', 0)->first();
        $db = DB_NINJA_1;

        if($company){

            foreach($company->accounts as $key => $account)
            {

                $data['companies'][$key]['id'] = $account->id;


            }

            $this->dispatch(new HostedMigration($user, $data, $db, true));

            $company->is_migrated = true;
            $company->save();
        }

    }
}