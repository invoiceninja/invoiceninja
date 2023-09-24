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

use App\Utils\Ninja;
use Livewire\Livewire;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Utils\TruthSource;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\App;
use App\Helpers\Mail\GmailTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use App\Http\Middleware\SetDomainNameDb;
use Illuminate\Queue\Events\JobProcessing;
use App\Helpers\Mail\Office365MailTransport;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // \DB::listen(function($query) {
        //     nlog(
        //         $query->sql,
        //         [
        //             'bindings' => $query->bindings,
        //             'time' => $query->time
        //         ]
        //     );
        // });

        // Model::preventLazyLoading(
        //     !$this->app->isProduction()
        // );

        /* Defines the name used in polymorphic tables */
        Relation::morphMap([
            'invoices'  => Invoice::class,
            'proposals' => Proposal::class,
        ]);

        Blade::if('env', function ($environment) {
            return config('ninja.environment') === $environment;
        });

        /* Sets default varchar length */
        Schema::defaultStringLength(191);

        /* Handles setting the correct database with livewire classes */
        if (Ninja::isHosted()) {
            Livewire::addPersistentMiddleware([
                SetDomainNameDb::class,
            ]);
        }

        /* Ensure we don't have stale state in jobs */
        Queue::before(function (JobProcessing $event) {
            App::forgetInstance('truthsource');
        });

        /* Always init a new instance everytime the container boots */
        app()->instance(TruthSource::class, new TruthSource());

        /* Extension for custom mailers */

        Mail::extend('gmail', function () {
            return new GmailTransport();
        });

        Mail::extend('office365', function () {
            return new Office365MailTransport();
        });

        Mailer::macro('postmark_config', function (string $postmark_key) {
            // @phpstan-ignore /** @phpstan-ignore-next-line **/
            Mailer::setSymfonyTransport(app('mail.manager')->createSymfonyTransport([
                'transport' => 'postmark',
                'token' => $postmark_key
            ]));
     
            return $this;
        });
        
    
        Mailer::macro('mailgun_config', function (string $secret, string $domain, string $endpoint = 'api.mailgun.net') {
            // @phpstan-ignore /** @phpstan-ignore-next-line **/
            Mailer::setSymfonyTransport(app('mail.manager')->createSymfonyTransport([ 
                'transport' => 'mailgun',
                'secret' => $secret,
                'domain' => $domain,
                'endpoint' => $endpoint,
                'scheme' => config('services.mailgun.scheme'),
            ]));
 
            return $this;
        });

    }

    public function register(): void
    {
        if (Ninja::isHosted()) {
            $this->app->register(\App\Providers\BroadcastServiceProvider::class);
        }
    }
}
