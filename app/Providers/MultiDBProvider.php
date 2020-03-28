<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Providers;

use App\Libraries\MultiDB;
use Illuminate\Support\ServiceProvider;

class MultiDBProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

        $this->app['events']->listen(
            \Illuminate\Queue\Events\JobProcessing::class,
            function ($event) {

                if (isset($event->job->payload()['db'])) {

                    \Log::error("Provider Setting DB = ".$event->job->payload()['db']);
                    \Log::error('Event Job '.$event->connectionName);
                    \Log::error(print_r($event->job,1));
                    \Log::error(print_r($event->job->payload(),1));

                    MultiDB::setDb($event->job->payload()['db']);
                }
            }
        );


        if ($this->app->runningInConsole()) {
            return;
        }

    }
}
