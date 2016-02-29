<?php namespace App\Auth;

use Illuminate\Auth\AuthServiceProvider;
use App\Auth\CustomerAuthManager;
use App\Auth\SiteGuard;

class CustomerAuthServiceProvider extends AuthServiceProvider
{
    public function register()
    {
        $this->app->alias('customerauth',        'App\Auth\CustomerAuthManager');
        $this->app->alias('customerauth.driver', 'App\Auth\SiteGuard');
        $this->app->alias('customerauth.driver', 'App\Contracts\Auth\SiteGuard');

        parent::register();
    }

    protected function registerAuthenticator()
    {
        $this->app->singleton('customerauth', function ($app) {
            $app['customerauth.loaded'] = true;

            return new CustomerAuthManager($app);
        });

        $this->app->singleton('customerauth.driver', function ($app) {
            return $app['customerauth']->driver();
        });
    }

    protected function registerUserResolver()
    {
        $this->app->bind('Illuminate\Contracts\Auth\Authenticatable', function ($app) {
            return $app['customerauth']->user();
        });
    }

    protected function registerRequestRebindHandler()
    {
        $this->app->rebinding('request', function ($app, $request) {
            $request->setUserResolver(function() use ($app) {
                return $app['customerauth']->user();
            });
        });
    }
}