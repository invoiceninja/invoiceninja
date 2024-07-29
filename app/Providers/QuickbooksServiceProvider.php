<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use QuickBooksOnline\API\DataService\DataService;
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
        $this->app->singleton(QuickbooksInterface::class, function ($app) {
            // TODO: Possibly load tokens from Cache or DB?
            $sdk = DataService::Configure(config('services.quickbooks.settings'));
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
        $this->registerConfig();
    }

    protected function registerConfig() {
        config()->set( 'services.quickbooks' , 
               ['settings' => [
                    'auth_mode' => 'oauth2',
                    'ClientID' => env('QUICKBOOKS_CLIENT_ID', false),
                    'ClientSecret' => env('QUICKBOOKS_CLIENT_SECRET', false),
                    'RedirectURI' => env('QUICKBOOKS_REDIRECT_URL', env('APP_URL')),
                    'scope' => "com.intuit.quickbooks.accounting",
                    'baseUrl' => ucfirst(env('APP_ENV'))
               ],
               'debug' => env('APP_DEBUG') || env('APP_ENV')
               ]
        );
    }
}
