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

namespace App\Console\Commands;

use App\Jobs\Util\VersionCheck;
use App\Utils\Ninja;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PostUpdate extends Command
{
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

        info('finished migrating');

        $output = [];

        // exec('vendor/bin/composer install --no-dev -o', $output);

        info(print_r($output, 1));
        info('finished running composer install ');

        try {
            Artisan::call('optimize');
        } catch (\Exception $e) {
            info("I wasn't able to optimize.");
        }

        info('optimized');

        try {
            Artisan::call('view:clear');
        } catch (\Exception $e) {
            info("I wasn't able to clear the views.");
        }

        info('view cleared');

        try {
            Artisan::call('queue:restart');
        } catch (\Exception $e) {
            info("I wasn't able to restart the queue.");
        }

        info('queue restarted');

        VersionCheck::dispatch();

        info('Sent for version check');
    }
}
