<?php

namespace App\Providers;

use App\Utils\ClientPortal\CustomMessage\CustomMessage;
use Illuminate\Support\ServiceProvider;

class ClientPortalServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        app()->bind('customMessage', function () {
            return new CustomMessage();
        });

    }
}
