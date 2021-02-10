<?php

namespace App\Providers;

use App\Helpers\Mail\GmailTransportManager;
use Illuminate\Mail\MailServiceProvider as MailProvider;
use Illuminate\Mail\TransportManager;

class MailServiceProvider extends MailProvider
{

    public function register()
    {
        $this->registerIlluminateMailer();
    }

    protected function registerIlluminateMailer()
    {
        $this->app->singleton('mail.manager', function($app) {
            return  new GmailTransportManager($app);
        });


        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });
    }

}

