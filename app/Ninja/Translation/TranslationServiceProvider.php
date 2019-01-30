<?php

namespace App\Ninja\Translation;

class TranslationServiceProvider extends \Illuminate\Translation\TranslationServiceProvider
{

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        $this->app->singleton('translation.loader', function ($app) {
            return new FileLoader($app['files'], $app['path.lang'], storage_path("lang"));
        });
    }
}