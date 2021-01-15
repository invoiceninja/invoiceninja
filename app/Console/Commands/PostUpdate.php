<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Console\Commands;

use App\Jobs\Util\VersionCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PostUpdate extends Command
{
    protected $name = 'ninja:post-update';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:post-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run basic upgrade commands';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        set_time_limit(0);

        nlog('running post update');

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Exception $e) {
            nlog("I wasn't able to migrate the data.");
        }

        nlog("finished migrating");

        exec('vendor/bin/composer install --no-dev');

        nlog("finished running composer install ");


        try {
            Artisan::call('optimize');
        } catch (\Exception $e) {
            nlog("I wasn't able to optimize.");
        }

        nlog("optimized");

        try {
            Artisan::call('view:clear');
        } catch (\Exception $e) {
            nlog("I wasn't able to clear the views.");
        }

        nlog("view cleared");

        /* For the following to work, the web user (www-data) must own all the directories */

        VersionCheck::dispatch();

        nlog("sent for version check");
        
        // echo "Done.";
    }
}
