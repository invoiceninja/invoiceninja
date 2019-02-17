<?php

namespace App\Http\ViewComposers;

use App\Models\Country;
use App\Models\Currency;
use App\Models\PaymentTerm;
use Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;


class TranslationComposer
{
    /**
     * Bind data to the view.
     *
     * @param View $view
     *
     * @return void
     */
    public function compose(View $view) :void
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

        $view->with('payment_types', Cache::get('paymentTypes')->each(function ($pType) {
            $pType->name = trans('texts.payment_type_'.$pType->name);
        })->sortBy(function ($pType) {
            return $pType->name;
        }));

        $view->with('languages', Cache::get('languages')->each(function ($lang) {
            $lang->name = trans('texts.lang_'.$lang->name);
        })->sortBy(function ($lang) {
            return $lang->name;
        }));

        $view->with('currencies', Cache::get('currencies')->each(function ($currency) {
            $currency->name = trans('texts.currency_' . Str::slug($currency->name, '_'));
        })->sortBy(function ($currency) {
            return $currency->name;
        }));

        $view->with('payment_terms', PaymentTerm::getCompanyTerms()->map(function ($term){
            $term['name'] = trans('texts.payment_terms_net') . ' ' . $term['num_days'];
            return $term;
        }));

    }

}
