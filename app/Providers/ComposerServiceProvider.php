<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ComposerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        /*
        view()->composer('*', 'App\Http\ViewComposers\HeaderComposer');

        view()->composer(
            [
                'client.edit',
            ],
            'App\Http\ViewComposers\TranslationComposer'
        );
        */
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
