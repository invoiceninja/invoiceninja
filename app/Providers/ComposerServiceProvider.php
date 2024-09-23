<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Providers;

use App\Http\ViewComposers\PortalComposer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;

class ComposerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        view()->composer('portal.*', PortalComposer::class);

        view()->composer(['*.rotessa.components.address','*.rotessa.components.banks.US.bank','*.rotessa.components.dropdowns.country.US'], function ($view) {
            $states = USStates::get();
            $view->with('states', $states);
        });

        view()->composer(['*.rotessa.components.address','*.rotessa.components.banks.CA.bank','*.rotessa.components.dropdowns.country.CA'], function ($view) {
            $provinces = CAProvinces::get();
            $view->with('provinces', $provinces);
        });

        Blade::componentNamespace('App\\Http\\ViewComposers\\Components\\Rotessa', 'rotessa');
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
