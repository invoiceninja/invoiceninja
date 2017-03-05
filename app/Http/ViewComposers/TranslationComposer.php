<?php

namespace App\Http\ViewComposers;

use Cache;
use Illuminate\View\View;

/**
 * TranslationComposer.php.
 *
 * @copyright See LICENSE file that was distributed with this source code.
 */
class TranslationComposer
{
    /**
     * Bind data to the view.
     *
     * @param View $view
     *
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('industries', Cache::get('industries')->each(function ($industry) {
            $industry->name = trans('texts.industry_'.$industry->name);
        })->sortBy(function ($industry) {
            return $industry->name;
        }));

        $view->with('countries', Cache::get('countries')->each(function ($country) {
            $country->name = trans('texts.country_'.$country->name);
        })->sortBy(function ($country) {
            return $country->name;
        }));

        $view->with('paymentTypes', Cache::get('paymentTypes')->each(function ($pType) {
            $pType->name = trans('texts.payment_type_'.$pType->name);
        })->sortBy(function ($pType) {
            return $pType->name;
        }));

        $view->with('languages', Cache::get('languages')->each(function ($lang) {
            $lang->name = trans('texts.lang_'.$lang->name);
        })->sortBy(function ($lang) {
            return $lang->name;
        }));
    }
}
