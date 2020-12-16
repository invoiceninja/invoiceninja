<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Console\Commands;

use App\Jobs\Util\VersionCheck;
use Composer\Console\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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

        info('running post update');

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Exception $e) {
            info("I wasn't able to migrate the data.");
        }

        info("finished migrating");

        putenv('COMPOSER_HOME=' . __DIR__ . '/vendor/bin/composer');

        $input = new ArrayInput(['command' => 'install', '--no-dev' => 'true']);
        $application = new Application();
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $application->run($input, $output);
        
        info("finished running composer install ");
        
        info(print_r($output->fetch(), 1));

        try {
            Artisan::call('optimize');
        } catch (\Exception $e) {
            info("I wasn't able to optimize.");
        }

        info("optimized");

        try {
            Artisan::call('view:clear');
        } catch (\Exception $e) {
            info("I wasn't able to clear the views.");
        }

        info("view cleared");

        /* For the following to work, the web user (www-data) must own all the directories */

        VersionCheck::dispatch();

        info("sent for version check");
        
        echo "Done.";
    }
}
