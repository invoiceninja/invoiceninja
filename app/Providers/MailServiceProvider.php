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

use App\Utils\CssInlinerPlugin;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Container\Container;
use Illuminate\Mail\MailServiceProvider as MailProvider;
use Illuminate\Mail\TransportManager;

class MailServiceProvider extends MailProvider
{

    public function boot()
    {
        // app('mail.manager')->getSymfonyTransport()->registerPlugin($this->app->make(CssInlinerPlugin::class));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerIlluminateMailer();
        $this->registerMarkdownRenderer();
    }

    public function registerGmailApiMailer()
    {

        // $factory = new GmailApiTransportFactory();

        // if (! isset($config['secret'])) {
        //     $config = $this->app['config']->get('services.mailgun', []);
        // }

        // return $factory->create(new Dsn(
        //     'gmail+api'),
        //     $config['endpoint'] ?? 'default',
        //     $config['secret'],
        //     $config['domain']
        // ));

    }

    public function registerMicrosoftMailer()
    {

    }
}
