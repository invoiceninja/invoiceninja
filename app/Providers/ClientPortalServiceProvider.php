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
        app()->bind('customMessage', function () {
            return new CustomMessage();
        });

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

    }
}
