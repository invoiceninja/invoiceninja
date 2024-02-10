<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Providers;

use App\Http\ViewComposers\PortalComposer;
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
        view()->composer('portal.*', PortalComposer::class);

        // view()->composer(
        //     ['email.admin.generic', 'email.client.generic'],
        //     function ($view) {
        //         $view->with(
        //             'template',
        //             Ninja::isHosted()
        //         );
        //     }
        // );


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
