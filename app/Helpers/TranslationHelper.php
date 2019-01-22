<?php

use Illuminate\Support\Facades\Cache;

/**
 * Returns a custom translation string
 * falls back on defaults if no string exists
 *
 * //Cache::forever($custom_company_translated_string, 'mogly');
 * 
 * @param  string translation string key
 * @return string
 */
function ctrans(string $string) : string
{

	$custom_company_translated_string = session('current_company_id') . '-' . $string;

	if (Cache::has($custom_company_translated_string)) 
    	return Cache::get($custom_company_translated_string);
	

    return trans($string);

}