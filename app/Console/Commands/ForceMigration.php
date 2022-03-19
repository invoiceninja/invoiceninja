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
    // define('DB_NINJA_1', 'db-ninja-1');
    // define('DB_NINJA_2', 'db-ninja-2');

    public $db = DB_NINJA_1;

    public $force = false;

    public $user = false;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:force-migrate-v5 {--email=} {--force=} {--database=}';

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

        if($this->option('database'))
            $this->db = $this->option('database');

        if($this->option('force'))
            $this->force = $this->option('force');

        if(!Utils::isNinjaProd())
            return;

        config(['database.default' => $this->db]);

        $company = $this->getCompany();

        if(!$company){

            $this->logMessage('Could not find a company with that email address');
            exit;
        
        }

        $this->forceMigrate($company);

    }


    private function getCompany()
    {

        if($this->option('email')){

            $user = User::on($this->db)
                  ->where('email', $this->option('email'))
                  ->whereNull('public_id')
                  ->orWhere('public_id', 0)
                  ->first();

            if(!$user){
                $this->logMessage('Could not find an owner user with that email address');
                exit;
            }

            $this->user = $user;

            return $user->account->company;

        }

        // $company = Company::on($this->db)
        //                   ->whereNull('plan')
        //                   ->orWhereIn('plan', ['','free'])
        //                   ->whereHas('accounts', function ($query){
        //                     $query->where('account_key', '!=', 'zg4ylmzDkdkPOT8yoKQw9LTWaoZJx79h');
        //                     $query->orWhere('account_key', '!=', 'zg4ylmzDkdkPOT8yoKQw9LTWaoZJx702');
        //                     $query->orWhere('account_key', '!=', 'AsFmBAeLXF0IKf7tmi0eiyZfmWW9hxMT');
        //                    })
        //                   ->with('accounts')
        //                   ->withCount('accounts')
        //                   ->having('accounts_count', '>=', 1)
        //                   ->first();

        // return $company;

    }

    private function logMessage($str)
    {
        $str = date('Y-m-d h:i:s').' '.$str;
        $this->info($str);
        $this->log .= $str."\n";
    }

    private function forceMigrate($company)
    {
        $data = [];

        if(!$this->user)
            $this->user = $company->accounts->first()->users()->whereNull('public_id')->orWhere('public_id', 0)->first();


        if(!$this->user){
            $this->logMessage('Could not find an owner user with that email address');
            exit;
        }

        if($company){

            foreach($company->accounts as $key => $account)
            {

                $data['companies'][$key]['id'] = $account->id;
                $data['companies'][$key]['force'] = $this->force;

            }

            $this->dispatch(new HostedMigration($this->user, $data, $this->db, true));

            $company->is_migrated = true;
            $company->save();
        }

    }
}