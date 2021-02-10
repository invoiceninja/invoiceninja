<?php

namespace App\Providers;

use App\Helpers\Mail\GmailTransportManager;
use Illuminate\Mail\MailServiceProvider as MailProvider;

class MailServiceProvider extends MailProvider
{

    public function register()
    {
        $this->registerSwiftTransport();
    }

    protected function registerSwiftTransport()
    {

        $this->app->singleton('swift.transport', function ($app) {
            return new GmailTransportManager($app);
        });
    }
}
