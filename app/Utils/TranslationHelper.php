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

namespace App\Utils;

use Illuminate\Support\Facades\Cache;
use App\Models\PaymentTerm;
use Illuminate\Support\Str;

class TranslationHelper
{
    public static function getIndustries()
    {
        return Cache::get('industries')->each(function ($industry) {
            $industry->name = ctrans('texts.industry_'.$industry->name);
        })->sortBy(function ($industry) {
            return $industry->name;
        });
    }

    public static function getCountries()
    {
        return Cache::get('countries')->each(function ($country) {
            $country->name = ctrans('texts.country_'.$country->name);
        })->sortBy(function ($country) {
            return $country->iso_3166_2;
        });
    }

    public static function getPaymentTypes()
    {
        return Cache::get('payment_types')->each(function ($pType) {
            $pType->name = ctrans('texts.payment_type_'.$pType->name);
        })->sortBy(function ($pType) {
            return $pType->name;
        });
    }

    public static function getLanguages()
    {
        return Cache::get('languages')->each(function ($lang) {
            $lang->name = ctrans('texts.lang_'.$lang->name);
        })->sortBy(function ($lang) {
            return $lang->name;
        });
    }

    public static function getCurrencies()
    {
        return Cache::get('currencies')->each(function ($currency) {
            $currency->name = ctrans('texts.currency_'.Str::slug($currency->name, '_'));
        })->sortBy(function ($currency) {
            return $currency->name;
        });
    }

    public static function getPaymentTerms()
    {
        return PaymentTerm::getCompanyTerms()->map(function ($term) {
            $term['name'] = ctrans('texts.payment_terms_net').' '.$term['num_days'];

            return $term;
        });
    }
}
