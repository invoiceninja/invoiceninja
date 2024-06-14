<?php

namespace App\PaymentDrivers\Rotessa\Providers;

use Illuminate\Support\Facades\Blade;
use App\PaymentDrivers\Rotessa\Events\CacheGateways;
use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    protected string $moduleName = 'Rotessa';

    protected string $moduleNameLower = 'rotessa';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        include __DIR__ . "/../Helpers/helpers.php";

        $this->registerConfig();
        $this->registerTranslations();
        $this->registerViews();

        event(new CacheGateways);
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
       $this->mergeConfigFrom(app_path("PaymentDrivers/{$this->moduleName}/config/gateway_types.php"),$this->moduleNameLower);
    }
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);

    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(app_path("PaymentDrivers/{$this->moduleName}resources/lang"), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(app_path("PaymentDrivers/{$this->moduleName}resources/lang"));
        }
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/portal/ninja2020/gateways/'.$this->moduleNameLower);
        $sourcePath = app_path('PaymentDrivers/Rotessa/resources/views/gateways/rotessa');
        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
        Blade::componentNamespace('App\\PaymentDrivers\\Rotessa\\View\\Components', $this->moduleNameLower);
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [app_path('PaymentDrivers/Rotessa/resources/views/gateways/rotessa')];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/'.$this->moduleNameLower)) {
                $paths[] = $path.'/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
