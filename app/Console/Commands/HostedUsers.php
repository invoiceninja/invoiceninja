<?php

namespace App\Console\Commands;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Utils\Ninja;
use Illuminate\Console\Command;

class HostedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:sync-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs Invoice Ninja Users';

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
     * @return int
     */
    public function handle()
    {
        Company::on('db-ninja-01')->each(function ($company) {
            if (Ninja::isHosted()) {
                (new \Modules\Admin\Jobs\Account\NinjaUser([], $company))->handle();
            }
        });

        Company::on('db-ninja-02')->each(function ($company) {
            if (Ninja::isHosted()) {
                (new \Modules\Admin\Jobs\Account\NinjaUser([], $company))->handle();
            }
        });
    }
}
