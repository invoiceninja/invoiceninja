<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ComposerServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function boot()
    {
        view()->composer(
            ['accounts.details', 'clients.edit', 'payments.edit', 'invoices.edit', 'accounts.localization'],
            'App\Http\ViewComposers\TranslationComposer'
        );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }
}