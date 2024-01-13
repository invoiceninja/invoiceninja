<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class VendorLocale
{
    private array $locales = [
                    'en',
                    'it',
                    'de',
                    'fr',
                    'pt_BR',
                    'nl',
                    'es',
                    'nb_NO',
                    'da',
                    'ja',
                    'sv',
                    'es_ES',
                    'fr_CA',
                    'lt',
                    'pl',
                    'cs',
                    'hr',
                    'sq',
                    'el',
                    'en_GB',
                    'pt_PT',
                    'sl',
                    'fi',
                    'ro',
                    'tr_TR',
                    'th',
                    'mk_MK',
                    'zh_TW',
                    'ru_RU',
                    'ar',
                    'fa',
                    'lv_LV',
                    'sr',
                    'sk',
                    'et',
                    'bg',
                    'he',
                    'km_KH',
                    'hu',
                    'fr_CH',
                    'lo_LA',
                ];
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (auth()->guard('contact')->check()) {
            auth()->guard('contact')->logout();
            $request->session()->invalidate();
        }

        /*LOCALE SET */
        if ($request->has('lang') && in_array($request->input('lang', 'en'), $this->locales)) {
            $locale = $request->input('lang');
            App::setLocale($locale);
        } elseif (auth()->guard('vendor')->user()) {
            App::setLocale(auth()->guard('vendor')->user()->vendor->locale());
        } elseif (auth()->user()) {
            try {
                App::setLocale(auth()->user()->company()->getLocale());
            } catch (\Exception $e) {
            }
        } else {
            App::setLocale(config('ninja.i18n.locale'));
        }

        return $next($request);
    }
}
