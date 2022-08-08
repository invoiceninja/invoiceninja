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
use Illuminate\Container\Container;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

class MailCssInlinerServiceProvider extends ServiceProvider
{
    // Thanks to @fedeisas/laravel-mail-css-inliner

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/css-inliner.php' => base_path('config/css-inliner.php'),
        ], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // $this->app->singleton(CssInlinerPlugin::class, function ($app) {
        //     return new CssInlinerPlugin([]);
        // });

        // $this->app->singleton(CssInlinerPlugin::class, function ($app) {
        //     return new CssInlinerPlugin([]);
        // });

        $this->app->bind(CssInlinerPlugin::class, function ($app) {
            return new CssInlinerPlugin([]);
        });
    }
}
