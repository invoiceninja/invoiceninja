<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as BaseProvider;

class RotessaServiceProvider extends BaseProvider
{
    protected string $moduleName = 'Rotessa';

    protected string $moduleNameLower = 'rotessa';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        include_once app_path('Http/ViewComposers/RotessaComposer.php');

        $this->registerComponent();
    }

    /**
     * Register views.
     */
    public function registerComponent(): void
    {
        Blade::componentNamespace('App\\Http\\ViewComposers\\Components\\Rotessa', $this->moduleNameLower);
    }
}
