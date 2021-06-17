<?php

namespace App\Providers;

use App\Utils\CssInlinerPlugin;
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
            __DIR__ . '/../config/css-inliner.php' => base_path('config/css-inliner.php'),
        ], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CssInlinerPlugin::class, function ($app) {
            return new CssInlinerPlugin([]);
        });

        // $this->app->afterResolving('mail.manager', function (MailManager $mailManager) {
        //     $mailManager->getSwiftMailer()->registerPlugin($this->app->make(CssInlinerPlugin::class));
        //     return $mailManager;
        // });
    }
}
