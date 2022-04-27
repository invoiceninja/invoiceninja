<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Providers;

use App\Helpers\Mail\GmailTransportManager;
use App\Utils\CssInlinerPlugin;
use Coconuts\Mail\PostmarkTransport;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Mail\MailServiceProvider as MailProvider;
use Illuminate\Mail\TransportManager;
use Illuminate\Container\Container;

class MailServiceProvider extends MailProvider
{

    public function register()
    {
        $this->registerIlluminateMailer();
    }

    public function boot()
    {
        app('mail.manager')->getSwiftMailer()->registerPlugin($this->app->make(CssInlinerPlugin::class));
    }

    protected function registerIlluminateMailer()
    {
        // //this is not octane safe
        $this->app->singleton('mail.manager', function($app) {
            return new GmailTransportManager($app);
        });


        //this is octane ready - but is untested
        // $this->app->bind('mail.manager', function ($app){
        //     return new GmailTransportManager($app);
        // });

        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        $this->app['mail.manager']->extend('cocopostmark', function () {

            return new PostmarkTransport(
                $this->guzzle(config('postmark.guzzle', [])),
                config('postmark.secret')
            );

        });

    }
    
    protected function guzzle(array $config): HttpClient
    {
        return new HttpClient(array_merge($config, [
                'base_uri' => empty($config['base_uri'])
                    ? 'https://api.postmarkapp.com'
                    : $config['base_uri']
            ]));
    }

    public function provides()
    {
        return [
            'mail.manager',
            'mailer'        
        ];
    }
}
