<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
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
