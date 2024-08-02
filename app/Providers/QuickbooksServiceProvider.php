<?php

namespace App\Providers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use QuickBooksOnline\API\DataService\DataService;
use App\Http\Controllers\ImportQuickbooksController;
use App\Services\Import\Quickbooks\Service as QuickbooksService;
use App\Services\Import\Quickbooks\Auth as QuickbooksAuthService;
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
           // TODO: Load tokens from Cache and DB?
            $sdk = DataService::Configure(config('services.quickbooks.settings') + ['state' => Str::random(12)]);
            if(env('APP_DEBUG')) {
                $sdk->setLogLocation(storage_path("logs/quickbooks.log"));
                $sdk->enableLog();
            }

            $sdk->setMinorVersion("73");
            $sdk->throwExceptionOnError(true);

            return new QuickbooksSDKWrapper($sdk);
        });
        
        // Register SDKWrapper with DataService dependency
        $this->app->singleton(QuickbooksService::class, function ($app) {
            return new QuickbooksService($app->make(QuickbooksInterface::class));
        });

        $this->app->singleton(QuickbooksAuthService::class, function ($app) {
            return new QuickbooksAuthService($app->make(QuickbooksInterface::class));
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

        Route::middleware('api')
            ->namespace($this->app->getNamespace() . 'Http\Controllers')
            ->group(function () {
                Route::post('import/quickbooks', [ImportQuickbooksController::class, 'import'])->name('import.quickbooks');
                //Route::post('import/quickbooks/preimport', [ImportQuickbooksController::class, 'preimport'])->name('import.quickbooks.preimport');
            });
    }
}
