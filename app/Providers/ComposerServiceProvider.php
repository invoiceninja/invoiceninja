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
        include_once app_path('Http/ViewComposers/RotessaComposer.php');
        include_once app_path("Http/ViewComposers/Components/RotessaComponents.php");
        Blade::componentNamespace('App\\Http\\ViewComposers\\Components', 'rotessa');
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
