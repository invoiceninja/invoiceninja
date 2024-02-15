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

namespace App\Providers;

use App\Libraries\MultiDB;
use Illuminate\Queue\Events\JobProcessing;
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
            JobProcessing::class,
            function ($event) {
                if (isset($event->job->payload()['db'])) {
                    MultiDB::setDb($event->job->payload()['db']);
                }
            }
        );
    }
}
