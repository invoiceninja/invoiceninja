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

use Composer\Composer;
use Composer\Console\Application;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\NullIO;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Log;
use Symfony\Component\Console\Input\ArrayInput;

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
        } catch (Exception $e) {
            Log::error("I wasn't able to migrate the data.");
        }

        try {
            Artisan::call('optimize');
        } catch (Exception $e) {
            Log::error("I wasn't able to optimize.");
        }

        /* For the following to work, the web user (www-data) must own all the directories */

        putenv('COMPOSER_HOME=' . __DIR__ . '/vendor/bin/composer');

        $input = new ArrayInput(array('command' => 'install', '--no-dev' => 'true'));
        $application = new Application();
        $application->setAutoExit(false);
        $application->run($input);

        echo "Done.";

    }
}
