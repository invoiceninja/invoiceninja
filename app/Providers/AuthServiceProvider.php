<?php

namespace App\Providers;

use App\Models\Client;
use App\Policies\ClientPolicy;
use Auth;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Client::class => ClientPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */

    public function boot()
    {
        $this->registerPolicies();

        Auth::provider('users', function ($app, array $config) {
            return new MultiDatabaseUserProvider($this->app['hash'], $config['model']);
        });

        Auth::provider('contacts', function ($app, array $config) {
            return new MultiDatabaseUserProvider($this->app['hash'], $config['model']);

        });
    }

}
