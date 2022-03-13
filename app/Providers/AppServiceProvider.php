<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Providers;

use App\Http\Middleware\SetDomainNameDb;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Utils\Ninja;
use App\Utils\TruthSource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        /* Limits the number of parallel jobs fired per minute when checking data*/
        RateLimiter::for('checkdata', function ($job) {
            return  Limit::perMinute(100);
        });

        Relation::morphMap([
            'invoices'  => Invoice::class,
          //  'credits'   => \App\Models\Credit::class,
            'proposals' => Proposal::class,
        ]);

        Blade::if('env', function ($environment) {
            return config('ninja.environment') === $environment;
        });

        Schema::defaultStringLength(191);

        /* Handles setting the correct database with livewire classes */
        if(Ninja::isHosted())
        {
            Livewire::addPersistentMiddleware([
                SetDomainNameDb::class,
            ]);
        }

        // Queue::before(function (JobProcessing $event) {
        //     // \Log::info('Event Job '.$event->connectionName);
        //     \Log::error('Event Job '.$event->job->getJobId);
        //     // \Log::info('Event Job '.$event->job->payload());
        // });
        //! Update Posted AT
        // Queue::after(function (JobProcessed $event) {
        //     // \Log::info('Event Job '.$event->connectionName);
        //     \Log::error('Event Job '.$event->job->getJobId);
        //     // \Log::info('Event Job '.$event->job->payload());
        // });
 
        app()->instance(TruthSource::class, new TruthSource());

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->loadHelpers();
    }

    protected function loadHelpers()
    {
        foreach (glob(__DIR__.'/../Helpers/*.php') as $filename) {
            require_once $filename;
        }
    }
}
