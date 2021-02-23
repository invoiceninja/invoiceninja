<?php

namespace App\Providers;

use App\Helpers\Mail\GmailTransportManager;
use Coconuts\Mail\PostmarkTransport;
use Illuminate\Mail\MailServiceProvider as MailProvider;
use Illuminate\Mail\TransportManager;
use GuzzleHttp\Client as HttpClient;

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

        $this->app['mail.manager']->extend('postmark', function () {
            return new PostmarkTransport(
                $this->guzzle(config('postmark.guzzle', [])),
                config('postmark.secret', config('services.postmark.secret'))
            );
        });
    }
    
    protected function guzzle(array $config): HttpClient
    {
        return new HttpClient($config);
    }
}

