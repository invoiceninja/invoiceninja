<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\ScoutServiceProvider as BaseScoutServiceProvider;

class ScoutServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Only register Scout if driver is not null
        if (config('scout.driver') !== 'null') {
            $this->app->register(BaseScoutServiceProvider::class);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Scout will be booted automatically by the base provider if driver is not null
    }
}
