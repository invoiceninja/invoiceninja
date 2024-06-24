<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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
        
        include_once app_path('PaymentDrivers/Rotessa/vendor/autoload.php');

        class_alias("App\\PaymentDrivers\\Rotessa\\PaymentMethod","App\\PaymentDrivers\\Rotessa\\BankTransfer");
        class_alias("App\\PaymentDrivers\\Rotessa\\PaymentMethod","App\\PaymentDrivers\\Rotessa\\Acss");

        $this->registerViews();
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/portal/ninja2020/gateways/'.$this->moduleNameLower);
        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$viewPath]), $this->moduleNameLower);
        Blade::componentNamespace('App\\Http\\ViewComposers\\Components', $this->moduleNameLower);
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/'.$this->moduleNameLower)) {
                $paths[] = $path.'/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
