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

namespace App\Utils;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Statics.
 */
class Statics
{
    /**
     * Date format types.
     * @var array
     */
    public static $date = [
        ['format' => 'd/M/Y', 'picker_format' => 'dd/M/yyyy', 'format_moment' => 'DD/MMM/YYYY', 'format_dart' => 'dd/MMM/yyyy'],
        ['format' => 'd-M-Y', 'picker_format' => 'dd-M-yyyy', 'format_moment' => 'DD-MMM-YYYY', 'format_dart' => 'dd-MMM-yyyy'],
        ['format' => 'd/F/Y', 'picker_format' => 'dd/MM/yyyy', 'format_moment' => 'DD/MMMM/YYYY', 'format_dart' => 'dd/MMMM/yyyy'],
        ['format' => 'd-F-Y', 'picker_format' => 'dd-MM-yyyy', 'format_moment' => 'DD-MMMM-YYYY', 'format_dart' => 'dd-MMMM-yyyy'],
        ['format' => 'M j, Y', 'picker_format' => 'M d, yyyy', 'format_moment' => 'MMM D, YYYY', 'format_dart' => 'MMM d, yyyy'],
        ['format' => 'F j, Y', 'picker_format' => 'MM d, yyyy', 'format_moment' => 'MMMM D, YYYY', 'format_dart' => 'MMMM d, yyyy'],
        ['format' => 'D M j, Y', 'picker_format' => 'D MM d, yyyy', 'format_moment' => 'ddd MMM Do, YYYY', 'format_dart' => 'EEE MMM d, yyyy'],
        ['format' => 'Y-m-d', 'picker_format' => 'yyyy-mm-dd', 'format_moment' => 'YYYY-MM-DD', 'format_dart' => 'yyyy-MM-dd'],
        ['format' => 'd-m-Y', 'picker_format' => 'dd-mm-yyyy', 'format_moment' => 'DD-MM-YYYY', 'format_dart' => 'dd-MM-yyyy'],
        ['format' => 'm/d/Y', 'picker_format' => 'mm/dd/yyyy', 'format_moment' => 'MM/DD/YYYY', 'format_dart' => 'MM/dd/yyyy'],
        ['format' => 'd.m.Y', 'picker_format' => 'dd.mm.yyyy', 'format_moment' => 'D.MM.YYYY', 'format_dart' => 'dd.MM.yyyy'],
        ['format' => 'j. M. Y', 'picker_format' => 'd. M. yyyy', 'format_moment' => 'DD. MMM. YYYY', 'format_dart' => 'd. MMM. yyyy'],
        ['format' => 'j. F Y', 'picker_format' => 'd. MM yyyy', 'format_moment' => 'DD. MMMM YYYY', 'format_dart' => 'd. MMMM yyyy'],
    ];

    /**
     * Date Time Format types.
     * @var array
     */
    public static $date_time = [
        ['format' => 'd/M/Y g:i a', 'format_moment' => 'DD/MMM/YYYY h:mm:ss a', 'format_dart' => 'dd/MMM/yyyy h:mm a'],
        ['format' => 'd-M-Y g:i a', 'format_moment' => 'DD-MMM-YYYY h:mm:ss a', 'format_dart' => 'dd-MMM-yyyy h:mm a'],
        ['format' => 'd/F/Y g:i a', 'format_moment' => 'DD/MMMM/YYYY h:mm:ss a', 'format_dart' => 'dd/MMMM/yyyy h:mm a'],
        ['format' => 'd-F-Y g:i a', 'format_moment' => 'DD-MMMM-YYYY h:mm:ss a', 'format_dart' => 'dd-MMMM-yyyy h:mm a'],
        ['format' => 'M j, Y g:i a', 'format_moment' => 'MMM D, YYYY h:mm:ss a', 'format_dart' => 'MMM d, yyyy h:mm a'],
        ['format' => 'F j, Y g:i a', 'format_moment' => 'MMMM D, YYYY h:mm:ss a', 'format_dart' => 'MMMM d, yyyy h:mm a'],
        ['format' => 'D M jS, Y g:i a', 'format_moment' => 'ddd MMM Do, YYYY h:mm:ss a', 'format_dart' => 'EEE MMM d, yyyy h:mm a'],
        ['format' => 'Y-m-d g:i a', 'format_moment' => 'YYYY-MM-DD h:mm:ss a', 'format_dart' => 'yyyy-MM-dd h:mm a'],
        ['format' => 'd-m-Y g:i a', 'format_moment' => 'DD-MM-YYYY h:mm:ss a', 'format_dart' => 'dd-MM-yyyy h:mm a'],
        ['format' => 'm/d/Y g:i a', 'format_moment' => 'MM/DD/YYYY h:mm:ss a', 'format_dart' => 'MM/dd/yyyy h:mm a'],
        ['format' => 'd.m.Y g:i a', 'format_moment' => 'D.MM.YYYY h:mm:ss a', 'format_dart' => 'dd.MM.yyyy h:mm a'],
        ['format' => 'j. M. Y g:i a', 'format_moment' => 'DD. MMM. YYYY h:mm:ss a', 'format_dart' => 'd. MMM. yyyy h:mm a'],
        ['format' => 'j. F Y g:i a', 'format_moment' => 'DD. MMMM YYYY h:mm:ss a', 'format_dart' => 'd. MMMM yyyy h:mm a'],
    ];

    /**
     * Company statics.
     * @param  string|bool $locale The user locale
     * @return array          Array of statics
     */
    public static function company($locale = false) :array
    {
        $data = [];

        foreach (config('ninja.cached_tables') as $name => $class) {
            if (! Cache::has($name)) {

                // check that the table exists in case the migration is pending
                if (! Schema::hasTable((new $class())->getTable())) {
                    continue;
                }
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count()) {
                    Cache::forever($name, $tableData);
                }
            }

            $data[$name] = Cache::get($name);
        }

        if ($locale) {
            $data['industries'] = Cache::get('industries')->each(function ($industry) {
                $industry->name = ctrans('texts.industry_'.$industry->name);
            })->sortBy(function ($industry) {
                return $industry->name;
            })->values();

            $data['countries'] = Cache::get('countries')->each(function ($country) {
                $country->name = ctrans('texts.country_'.$country->name);
            })->sortBy(function ($country) {
                return $country->name;
            })->values();

            $data['payment_types'] = Cache::get('payment_types')->each(function ($pType) {
                $pType->name = ctrans('texts.payment_type_'.$pType->name);
            })->sortBy(function ($pType) {
                return $pType->name;
            })->values();

            $data['languages'] = Cache::get('languages')->each(function ($lang) {
                $lang->name = ctrans('texts.lang_'.$lang->name);
            })->sortBy(function ($lang) {
                return $lang->name;
            })->values();

            $data['currencies'] = Cache::get('currencies')->each(function ($currency) {
                $currency->name = ctrans('texts.currency_'.Str::slug($currency->name, '_'));
            })->sortBy(function ($currency) {
                return $currency->name;
            })->values();

            $data['templates'] = Cache::get('templates');
        }

        return $data;
    }
}
