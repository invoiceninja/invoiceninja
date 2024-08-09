<?php

namespace App\Providers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use App\Factory\QuickbooksSDKFactory;
use Illuminate\Support\ServiceProvider;
use App\Http\Controllers\ImportQuickbooksController;
use App\Services\Import\Quickbooks\Service as QuickbooksService;
use App\Repositories\Import\Quickcbooks\Contracts\RepositoryInterface;
use App\Services\Import\Quickbooks\SdkWrapper as QuickbooksSDKWrapper;
use App\Services\Import\Quickbooks\Contracts\SdkInterface as QuickbooksInterface;
use App\Services\Import\Quickbooks\Transformers\Transformer as QuickbooksTransformer;

class QuickbooksServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->bind(QuickbooksInterface::class, function ($app) {
            return new QuickbooksSDKWrapper(QuickbooksSDKFactory::create());
        });
        
        // Register SDKWrapper with DataService dependency
        $this->app->singleton(QuickbooksService::class, function ($app) {
           return new QuickbooksService($app->make(QuickbooksInterface::class));
        });

        $this->app->singleton(QuickbooksTransformer::class,QuickbooksTransformer::class);
    }
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRoutes();
        $this->registerConfig();
    }

    protected function registerConfig() {
        config()->set( 'services.quickbooks' , 
               ['settings' => [
                    'auth_mode' => 'oauth2',
                    'ClientID' => env('QUICKBOOKS_CLIENT_ID', false),
                    'ClientSecret' => env('QUICKBOOKS_CLIENT_SECRET', false),
                    // TODO use env('QUICKBOOKS_REDIRECT_URI') or route()/ url()
                    'RedirectURI' => url("/quickbooks/authorized"),
                    'scope' => "com.intuit.quickbooks.accounting",
                    'baseUrl' => ucfirst(env('APP_ENV'))
               ],
               'debug' => env('APP_DEBUG') || env('APP_ENV')
               ]
        );
    }

    /**
     * Register custom routes.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        Route::middleware('web')
            ->namespace($this->app->getNamespace() . 'Http\Controllers')
            ->group(function () {
                Route::get('quickbooks/authorize/{token}', [ImportQuickbooksController::class, 'authorizeQuickbooks'])->name('authorize.quickbooks');
                Route::get('quickbooks/authorized', [ImportQuickbooksController::class, 'onAuthorized'])->name('authorized.quickbooks');
            });
            Route::prefix('api/v1')
            ->middleware('api')
            ->namespace($this->app->getNamespace() . 'Http\Controllers')
            ->group(function () {
                Route::post('import/quickbooks', [ImportQuickbooksController::class, 'import'])->name('import.quickbooks');
            });
    }
}
