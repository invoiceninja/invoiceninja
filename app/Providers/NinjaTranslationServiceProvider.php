<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Providers;

use App\Helpers\Language\NinjaTranslator;
use Illuminate\Translation\TranslationServiceProvider;

class NinjaTranslationServiceProvider extends TranslationServiceProvider
{
	public function boot()
    {
       //parent::boot();

        $this->app->singleton('translator', function($app)
        {
            $loader = $app['translation.loader'];
            $locale = $app['config']['app.locale'];

            $trans = new NinjaTranslator($loader, $locale);

            $trans->setFallback($app['config']['app.fallback_locale']);

            return $trans;
        });

    }
}

