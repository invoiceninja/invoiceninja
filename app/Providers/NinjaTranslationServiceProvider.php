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

use App\Helpers\Language\NinjaTranslator;
use Illuminate\Translation\TranslationServiceProvider;

class NinjaTranslationServiceProvider extends TranslationServiceProvider
{
    public function boot()
    {

        /*
         * To reset the translator instance we call
         *
         * App::forgetInstance('translator');
         *
         * Why? As the translator is a singleton it persists for its
         * lifecycle
         *
         * We _must_ reset the singleton when shifting between
         * clients/companies otherwise translations will
         * persist.
         *
         */

        //this is not octane safe
        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];
            $locale = $app['config']['app.locale'];

            $trans = new NinjaTranslator($loader, $locale);

            $trans->setFallback($app['config']['app.fallback_locale']);

            return $trans;
        });
    }
}
