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

namespace App\Console\Commands;

use App\Models\Company;
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
