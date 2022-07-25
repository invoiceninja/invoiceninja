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

use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    use MakesHash;

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapContactApiRoutes();

        $this->mapVendorsApiRoutes();

        $this->mapClientApiRoutes();

        $this->mapShopApiRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('')
             ->middleware('api')
             ->group(base_path('routes/api.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapContactApiRoutes()
    {
        Route::prefix('')
             ->middleware('contact')
             ->group(base_path('routes/contact.php'));
    }

    /**
     * Define the "client" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapClientApiRoutes()
    {
        Route::prefix('')
             ->middleware('client')
             ->group(base_path('routes/client.php'));
    }

    protected function mapShopApiRoutes()
    {
        Route::prefix('')
             ->middleware('shop')
             ->group(base_path('routes/shop.php'));
    }

    protected function mapVendorsApiRoutes()
    {
        Route::prefix('')
            ->middleware('client')
            ->group(base_path('routes/vendor.php'));
    }
}
