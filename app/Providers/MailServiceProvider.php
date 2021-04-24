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

    public function boot()
    {

    }

    protected function registerIlluminateMailer()
    {
        $this->app->singleton('mail.manager', function($app) {
            return  new GmailTransportManager($app);
        });

        // $this->app->bind('mail.manager', function($app) {
        //     return  new GmailTransportManager($app);
        // });
        
        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        $this->app['mail.manager']->extend('postmark', function () {
             return new HttpClient(array_merge($config, [
                'base_uri' => empty($config['base_uri'])
                    ? 'https://api.postmarkapp.com'
                    : $config['base_uri']
            ]));
        });
        
    }
    
    protected function guzzle(array $config): HttpClient
    {
        return new HttpClient($config);
    }

    public function provides()
    {
        return [
            'mail.manager',
            'mailer'        ];
    }
}
